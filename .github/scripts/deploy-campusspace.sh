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
[[ -r .env && -z "$(git ls-files -- .env)" ]]
[[ -z "$(git status --porcelain --untracked-files=no)" ]]
php -r 'exit(PHP_VERSION_ID >= 80400 && extension_loaded("pdo_pgsql") ? 0 : 1);' || {
    printf 'The deployment account needs PHP 8.4 or later with pdo_pgsql.\n' >&2
    exit 1
}

previous=$(git rev-parse HEAD)
git fetch --no-tags origin refs/heads/main
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

health=$(curl --fail --silent --show-error --max-time 20 --retry 2 https://rkky.my.id/health/campusspace)
php -r '
$health = json_decode(stream_get_contents(STDIN), true);
exit(($health["app"] ?? null) === "CampusSpace" && ($health["ready"] ?? false) === true
    && ($health["release"] ?? null) === $argv[1] ? 0 : 1);
' "$revision" <<< "$health"
curl --fail --silent --show-error --max-time 20 --retry 2 --location https://rkky.my.id/ > "$stage/home.html"
grep -Fq 'data-app="campusspace"' "$stage/home.html"

printf 'CampusSpace %s is serving verified HTTPS content at rkky.my.id.\n' "$revision"
