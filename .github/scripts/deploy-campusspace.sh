#!/usr/bin/env bash
set -Eeuo pipefail

revision="${1:-}"
stage="${2:-}"
[[ "$revision" =~ ^[0-9a-f]{40}$ && "$stage" =~ ^/tmp/campusspace-deploy\.[A-Za-z0-9]{8}$ ]]
[[ -d "$stage" && "$(realpath -- "$stage")" == "$stage" && -s "$stage/assets.tar.gz" ]]
umask 022

project="$HOME/htdocs/rkky.my.id"
cd "$project"
project=$(pwd -P)
worktree="$stage/preflight"
previous=''
updated=false
cleanup() {
    local result=$?
    if [[ -e "$worktree/.git" ]]; then
        git -C "$project" worktree remove --force "$worktree" >/dev/null || true
    fi
    rm -rf -- "$stage"
    if (( result != 0 )); then
        if [[ "$updated" == true ]]; then
            printf 'Deployment failed after moving the live checkout from %s to %s. Inspect the failed step before retrying.\n' "$previous" "$revision" >&2
        else
            printf 'Deployment preflight failed before updating the live checkout.\n' >&2
        fi
    fi
}
trap cleanup EXIT

[[ "$(git rev-parse --show-toplevel)" == "$project" ]]
[[ "$(git symbolic-ref --short HEAD)" == 'main' ]]
origin=$(git remote get-url origin)
case "${origin%.git}" in
    https://github.com/RickykennardW/rkky-website|git@github.com:RickykennardW/rkky-website|ssh://git@github.com/RickykennardW/rkky-website) ;;
    *) printf 'The deployment path is not the expected repository.\n' >&2; exit 1 ;;
esac

exec 9>"$(git rev-parse --git-path campusspace-deploy.lock)"
flock -n 9 || { printf 'A CampusSpace deployment is already running.\n' >&2; exit 1; }
[[ -r .env && -z "$(git ls-files -- .env)" ]] || {
    printf 'Configure a readable, private .env on the server before deploying.\n' >&2
    exit 1
}
[[ -z "$(git status --porcelain --untracked-files=no)" ]] || {
    printf 'The server checkout has tracked local changes. Review them before deploying.\n' >&2
    exit 1
}
php -r 'exit(PHP_VERSION_ID >= 80400 && extension_loaded("pdo_pgsql") ? 0 : 1);' || {
    printf 'The deployment account needs PHP 8.4 or later with pdo_pgsql.\n' >&2
    exit 1
}

previous=$(git rev-parse HEAD)
GIT_TERMINAL_PROMPT=0 git fetch --no-tags origin refs/heads/main
[[ "$(git rev-parse FETCH_HEAD)" == "$revision" ]] || {
    printf 'The validated commit is no longer the tip of main. Deploy the latest validated run.\n' >&2
    exit 1
}
git merge-base --is-ancestor "$previous" "$revision"

mkdir "$stage/build"
tar --extract --gzip --file "$stage/assets.tar.gz" --directory "$stage/build" --no-same-owner --no-same-permissions
[[ -s "$stage/build/manifest.json" && -d "$stage/build/assets" ]]
if [[ -e public/storage || -L public/storage ]]; then
    [[ -L public/storage && "$(readlink -f -- public/storage)" == "$project/storage/app/public" ]] || {
        printf 'The existing public/storage entry must point to storage/app/public.\n' >&2
        exit 1
    }
fi

git worktree add --detach "$worktree" "$revision"
ln -s -- "$project/.env" "$worktree/.env"
ln -s -- "$stage/build" "$worktree/public/build"
(
    cd "$worktree"
    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
    php artisan campusspace:check-production --no-interaction
    php artisan migrate --force --no-interaction
    php artisan campusspace:check-production --schema --no-interaction
)

git merge --ff-only "$revision"
updated=true
mkdir -p vendor public/build/assets storage/app/public storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
cp -a -- "$worktree/vendor/." vendor/
cp -a -- "$stage/build/assets/." public/build/assets/
manifest=$(mktemp public/build/.manifest.XXXXXXXX)
install -m 644 "$stage/build/manifest.json" "$manifest"
mv -f -- "$manifest" public/build/manifest.json
if [[ -f public/hot || -L public/hot ]]; then rm -f -- public/hot; fi

php artisan config:clear --no-interaction
if [[ ! -L public/storage ]]; then php artisan storage:link --no-interaction; fi
php artisan optimize --no-interaction
php artisan campusspace:check-production --schema --no-interaction
release=$(mktemp storage/app/.campusspace-release.XXXXXXXX)
printf '%s\n' "$revision" > "$release"
chmod 644 "$release"
mv -f -- "$release" storage/app/campusspace-release

verify_https() {
    local path="$1" destination="$2" status result
    if status=$(curl --silent --show-error --max-time 20 --retry 1 --retry-max-time 25 \
        --proto '=https' --output "$destination" --write-out '%{http_code}' "https://rkky.my.id$path"); then
        case "$status" in
            200) return 0 ;;
            403) printf 'HTTPS returned 403 for %s. Check the CloudPanel public document root and permissions.\n' "$path" >&2 ;;
            404) printf 'HTTPS returned 404 for %s. Check the CloudPanel document root and Laravel route fallback.\n' "$path" >&2 ;;
            500|503) printf 'HTTPS returned %s for %s. Check the site PHP-FPM and Laravel logs.\n' "$status" "$path" >&2 ;;
            *) printf 'HTTPS returned %s for %s. Expected HTTP 200 from CampusSpace.\n' "$status" "$path" >&2 ;;
        esac
        return 1
    else
        result=$?
        if [[ "$result" -eq 60 ]]; then
            printf 'The public HTTPS certificate could not be verified. Check the certificate and chain in CloudPanel.\n' >&2
        fi
        return "$result"
    fi
}

printf 'CampusSpace %s is installed in the server checkout. Verifying the public domain.\n' "$revision"
verify_https /health/campusspace "$stage/health.json"
php -r '
$health = json_decode(stream_get_contents(STDIN), true);
exit(($health["app"] ?? null) === "CampusSpace" && ($health["ready"] ?? false) === true
    && ($health["release"] ?? null) === $argv[1] ? 0 : 1);
' "$revision" < "$stage/health.json" || {
    printf 'HTTPS health did not report the expected ready CampusSpace release.\n' >&2
    exit 1
}
verify_https / "$stage/home.html"
grep -Fq 'data-app="campusspace"' "$stage/home.html" || {
    printf 'HTTPS homepage is missing the CampusSpace layout marker.\n' >&2
    exit 1
}

printf 'CampusSpace %s is serving verified HTTPS content at rkky.my.id.\n' "$revision"
