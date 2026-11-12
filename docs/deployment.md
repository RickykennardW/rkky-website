# Deploy CampusSpace to rkky.my.id

Use the existing GitHub repository, `main` branch, CloudPanel site, and `rkkyweb` SSH account. The site should run PHP 8.4 with the Laravel extensions and `pdo_pgsql` for both CLI and PHP-FPM. The SSH account needs Composer, Git, curl, tar, flock, write access to the checkout, and read access to its origin repository.

## Prepare the existing server

Keep the active server `.env` private and edit it in place under `~/htdocs/rkky.my.id`. Retain the existing valid `APP_KEY` and any mail or database credentials still in use. Set the following production values.

```ini
APP_NAME=CampusSpace
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rkky.my.id
DB_CONNECTION=pgsql
DB_SSLMODE=require
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Set `DB_URL` to the existing Supabase PostgreSQL connection string or configure `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` as described in the README. Use the Session pooler on port 5432 when IPv4 is required. Set `SUPABASE_URL` and `SUPABASE_PUBLISHABLE_KEY` if existing password users will link their legacy accounts. Keep any retained `SUPABASE_SECRET_KEY` private. Use a signed Laravel email verification flow only after configuring working mail transport.

For the site administrator, set the CloudPanel Nginx **document root** to the absolute equivalent of `~/htdocs/rkky.my.id/public`. Confirm PHP 8.4 FPM serves `public/index.php` and the site sends requests for missing paths to `/index.php?$query_string` through Nginx. Static assets under `public/build/assets` must be readable. Ensure the site's PHP-FPM user can write to `storage` and `bootstrap/cache`. [Laravel Nginx and PHP-FPM requirements](https://laravel.com/framework/docs/13.x/deployment)

## GitHub Actions setup

In **Settings → Secrets and variables → Actions**, reuse these repository secrets from the previous workflow.

| Secret | Value |
| --- | --- |
| `SSH_HOST` | Hostname or address of the existing SSH server |
| `SSH_USERNAME` | `rkkyweb` |
| `SSH_PRIVATE_KEY` | Existing deployment private key with its public key installed for the site account |
| `SSH_KNOWN_HOSTS` | SSH host key entry verified with the server administrator |
| `SSH_PORT` | Optional SSH port. Defaults to 22. |

A known hosts entry can use the actual connection host or the verified `rkky.my.id` alias. SSH validates the stored key. Production app credentials belong in the server `.env`.

From the Windows workspace, review and publish the migration to the existing repository.

```powershell
git status --short
git check-ignore .env .env.local
git add -A
git diff --cached --stat
git commit -m "Migrate CampusSpace to Laravel"
git push origin main
```

Open **GitHub → Actions → Deploy CampusSpace → Run workflow**, select **main**, and click **Run workflow**. Pushes and pull requests to `main` run validation. After a successful manual deployment, set the repository Actions variable `CAMPUSSPACE_AUTO_DEPLOY=true` if you want validated pushes to `main` to deploy automatically.

The deployment locks the existing checkout and verifies the intended commit is the tip of `main`. A temporary worktree outside the site installs production Composer dependencies, checks the database connection, and applies the Laravel migrations. The workflow updates the live checkout only after the staged schema and compiled assets pass validation. It then publishes the assets, runs Laravel optimization, and checks the HTTPS health response and homepage content. Previous commits and hashed assets are available if a release needs rollback. To recover from a bad application commit, revert it on `main` and run the deployment again. The schema migrations retain existing data and are handled separately from code rollback.

For a fresh installation, the migration role needs database and schema creation privileges, access to `auth.uid()`, and `TRIGGER`, `REFERENCES (id)`, and profile backfill read access on `auth.users`. `php artisan migrate --pretend` reads real catalog metadata and previews the migration SQL. Use it against an initialized migration repository because Artisan can create the `migrations` table during preparation. The preview cannot test DDL execution or locks.

Apply the migrations once at a quiet time because creating the profile trigger locks `auth.users` during the first transaction. Each migration commits separately and has a five-second lock timeout. If the second migration fails, inspect `php artisan migrate:status`, address the cause, and resume with `php artisan migrate`.

## Verify and troubleshoot

The following checks can be run as `rkkyweb` after deployment.

```bash
cd ~/htdocs/rkky.my.id
php artisan campusspace:check-production --schema
php artisan migrate:status
curl -fsS https://rkky.my.id/health/campusspace
curl -fsS https://rkky.my.id/ | grep -F 'data-app="campusspace"'
```

A successful health response has `app` equal to `CampusSpace`, `ready` equal to `true`, and the deployed Git commit in `release`. The homepage marker confirms CloudPanel serves the CampusSpace Laravel layout. In the browser, sign in and check a room calendar against the existing inventory.

| Symptom | Check |
| --- | --- |
| CloudPanel default page or 403 | Confirm the domain points to the intended site, its document root ends in `/public`, and Nginx can traverse it and read `public/index.php`. |
| 404 on routes such as `/rooms` | Confirm the Nginx Laravel fallback and run `php artisan route:list` from the checkout. |
| 500 | Run `php artisan campusspace:check-production --schema`. Check the site PHP-FPM and Laravel logs privately for the specific exception. |
| PostgreSQL fails | Verify the existing database credentials, pooler port 5432, server reachability, `pdo_pgsql`, and `DB_SSLMODE=require`. |
| Missing or unstyled assets | Confirm `public/build/manifest.json` and its files were installed by the workflow. Production uses these assets. |
| HTTPS certificate fails validation | Check the site's certificate and chain in CloudPanel. Run the HTTPS checks again after the chain is trusted. |
