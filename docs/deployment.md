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

If the server still has its pre-CampusSpace environment, run the provisioning helper from a configured local checkout. Pass the path to the existing SSH identity for `rkkyweb`. The local SSH client must already trust the verified host key for `rkky.my.id`.

```powershell
php .github/scripts/provision-production-env.php <path-to-existing-SSH-identity>
```

The helper reads the active Laravel connection from the local checkout and verifies its Supabase migration ledger through a read-only connection. It builds `DB_URL` from the existing host, port, database, username, password, and TLS mode. It sends that URL, the Supabase API URL, and the publishable key through verified SSH standard input. It atomically updates the existing private server `.env` and retains the server `APP_KEY` and active PostgreSQL credentials. An existing `DB_URL` or complete Supabase connection settings under another driver require review before changing the driver. Git and GitHub Actions carry the application code and assets. The unused Supabase Secret Key stays in its private environment.

When a key has been exposed, rotate the affected environment with `php artisan key:generate`. For production, check encrypted application data before rotating and schedule for existing sessions to expire. Omit the exposed key from `APP_PREVIOUS_KEYS`. [Laravel encryption and key rotation](https://laravel.com/framework/docs/13.x/encryption#gracefully-rotating-encryption-keys)

## Presentation Demo Mode

Pushes to `main` deploy Demo Mode after CI passes. To run it manually, use **Actions → Deploy CampusSpace → Run workflow → main → demo**. The server release marker enables Demo Mode. The deployment keeps the existing private environment, checks PHP and Vite assets, and skips database migrations. Select **normal** manually only when PostgreSQL and `pdo_pgsql` are ready. Normal deployment checks PostgreSQL and removes the demo marker after the normal release passes preflight.

Open **Start demo**, choose **Student**, and use **Switch demo role** to review that browser's requests as the demo administrator. Signing out clears that browser's demo requests. The room and equipment catalog contains fictional data.

To rehearse locally after building the assets, run these commands in PowerShell.

```powershell
npm run build
$env:CAMPUSSPACE_DEMO_MODE='true'
php artisan serve --host=127.0.0.1 --port=8013
```

Open [the local demo](http://127.0.0.1:8013). When the rehearsal ends, stop the server and remove the environment variable from that terminal with `Remove-Item Env:CAMPUSSPACE_DEMO_MODE`.

## CloudPanel administrator setup

1. Enable PostgreSQL support for **PHP 8.4 CLI and PHP-FPM**. Install `php8.4-pgsql` on the host if the module is absent, and reload PHP-FPM. Confirm `php -m` lists `pdo_pgsql` in the site's SSH shell. Select PHP 8.4 in the site's **PHP Settings**. [CloudPanel site settings](https://www.cloudpanel.io/docs/v2/frontend-area/settings/)
2. In **Settings → Domain Settings**, set **Root Directory** to the site's `~/htdocs/rkky.my.id/public` directory. The site vhost should serve `public/index.php` through PHP-FPM and route missing paths to `/index.php?$query_string`. Ensure the site user can write `storage` and `bootstrap/cache` and serve `public/build/assets`. [Laravel Nginx and PHP-FPM requirements](https://laravel.com/framework/docs/13.x/deployment)
3. In **SSL/TLS**, issue a trusted certificate for `rkky.my.id`. For Let's Encrypt, choose **Actions → New Let's Encrypt Certificate**, add the domain, and select **Create and Install**. [CloudPanel SSL/TLS settings](https://www.cloudpanel.io/docs/v2/frontend-area/tls/)

When CloudPanel terminates TLS directly, pass HTTPS through the site's FastCGI configuration. If a separate reverse proxy terminates TLS, configure Laravel to trust only that proxy's address or network and the headers it sets. [Laravel trusted proxies](https://laravel.com/framework/docs/requests#configuring-trusted-proxies)

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

After pushing to `main`, follow **GitHub → Actions → Deploy CampusSpace**. The deploy job starts after validation succeeds and uses **demo** for pushes. Pull requests targeting `main` run validation only. A manual run can deploy the current tip of `main` in either mode. Both modes share the same production deployment queue.

The deployment locks the existing checkout, rejects commits superseded on `main`, and only fast-forwards the server checkout. A temporary worktree outside the site installs production Composer dependencies and runs readiness checks. Only **normal** checks PostgreSQL and applies Laravel migrations. The workflow updates the live checkout after preflight passes, publishes the assets, runs Laravel optimization, and checks the HTTPS health response and homepage content. Previous commits and hashed assets are available if a release needs rollback. To recover from a bad application commit, revert it on `main` and deploy the revert. The schema migrations retain existing data and are handled separately from code rollback.

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
| Blade `touch(): Utime failed` | Inspect the owner of `storage/framework/views` and its compiled PHP files. The deployment removes only its own hashed Blade files and skips `view:cache` on the live checkout. PHP-FPM recompiles the views as `rkky` on demand. |
| PostgreSQL fails | Verify the existing database credentials, pooler port 5432, server reachability, `pdo_pgsql`, and `DB_SSLMODE=require`. |
| Missing or unstyled assets | Confirm `public/build/manifest.json` and its files were installed by the workflow. Production uses these assets. |
| HTTPS certificate fails validation | Check the site's certificate and chain in CloudPanel. Run the HTTPS checks again after the chain is trusted. |

Keep `storage/framework/views` writable and traversable by the site PHP-FPM user and the SSH deployment account. On `rkky.my.id`, these accounts share the `rkky` group. If the accounts lose access, ask the CloudPanel administrator to restore group write and traversal on `/home/rkky/htdocs/rkky.my.id/storage/framework/views` and verify traversal on its parent directories. Run any SSH-side `view:cache` in CI or a staging worktree. On the live checkout use `php artisan optimize --except=views` so PHP-FPM owns the compiled files it updates.
