# CampusSpace

CampusSpace manages university rooms, reservations, equipment loans, and event layouts. It runs on Laravel 13, PHP 8.4, PostgreSQL, Blade, Tailwind CSS, Alpine.js, and Vite. The production site uses PHP-FPM and the existing CloudPanel domain.

## Local setup on Windows

Install PHP 8.4 with `pdo_pgsql`, Composer 2, and Node.js 22.12 or newer. Use the commands from the repository root in PowerShell.

```powershell
composer install
if (!(Test-Path .env)) { Copy-Item .env.example .env }
npm ci
npm run build
```

If the local `APP_KEY` is empty, run `php artisan key:generate`. Laravel reads the root `.env` file by default. If you retained `.env.local` from Next.js, leave the shell's `APP_ENV` unset and omit `--env=local` when invoking Artisan so Laravel uses `.env`. Enter the PostgreSQL settings below, then start the application.

```powershell
php artisan migrate
php artisan serve
```

Open [the local app](http://127.0.0.1:8000). To rebuild assets while editing Blade or JavaScript, run `npm run dev` in a second terminal. For a build that serves the compiled assets through PHP, use `npm run build`.

## Connect the existing Supabase project

In the existing project, open **Connect → Session pooler** and copy the port 5432 connection string. Direct PostgreSQL is also supported when the server can reach it. Set `DB_CONNECTION=pgsql`, `DB_URL`, and `DB_SSLMODE=require` in `.env`. Fill in the existing database password and percent-encode any reserved URL characters in it. Alternatively leave `DB_URL` empty and set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` separately. The Session pooler username and hostname must come from the existing project's Connect panel. [Supabase PostgreSQL connection modes](https://supabase.com/docs/guides/database/connecting-to-postgres)

| Variable | Purpose |
| --- | --- |
| `SUPABASE_URL` | Existing project's HTTPS API URL, used when linking a legacy Supabase Auth account |
| `SUPABASE_PUBLISHABLE_KEY` | Existing publishable key for legacy password verification |
| `SUPABASE_SECRET_KEY` | Optional retained server credential. The application does not consume it. |

The Next.js names `NEXT_PUBLIC_SUPABASE_URL` and `NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY` correspond to the first two Laravel names. Both `.env` and `.env.local` are ignored by Git. PostgreSQL authentication uses its own password or connection string.

Laravel's first migration installs the reviewed CampusSpace SQL baseline on an empty Supabase schema or verifies and adopts the existing CampusSpace tables and booking functions. A partial or incompatible schema stops deployment. The second migration keeps existing profile IDs and booking references, releases the legacy constraint requiring every profile to have a Supabase Auth user, and creates private tables for Laravel credentials and notifications. Running the old SQL manually is only necessary for installations that are still using the previous application.

### Accounts and catalog

New users register through Laravel, with hashed passwords and a student role. To link a confirmed Supabase Auth account with a password, configure the existing API URL and publishable key. At first login Laravel verifies that password with Supabase and links only the matching email and profile UUID. The account keeps its bookings and role. Subsequent logins use Laravel's password hash and account lifecycle. Supabase Auth changes, including disabling an account or enabling MFA, require separate handling for linked Laravel accounts. Accounts with an unconfirmed email or an external identity provider need verified account recovery before linking.

Optional Laravel email verification uses `CAMPUSSPACE_VERIFY_EMAIL=true` and the application's configured mail transport. Laravel sends signed verification links to the app URL.

After a trusted administrator has signed in or registered, run:

```powershell
php artisan campusspace:make-admin <existing-account-email>
```

Admin screens can add rooms with measured dimensions, facilities and inventory. For a **disposable demo** catalog, run:

```powershell
php artisan db:seed --class=DemoCatalogSeeder
```

The sample catalog adds three rooms and seven equipment types. It uses fixed demo IDs and leaves conflicts in place. The planner generates a 2D estimate from the recorded geometry, capacity, furniture and clearance. Verify the actual layout with campus staff.

## Isolated PostgreSQL testing

Create an empty PostgreSQL database named `campusspace_test` on loopback. PHPUnit defaults to port 55432 and the local `postgres` user. Set the port and local test credentials in the current PowerShell session if needed. The test harness initializes the small Supabase Auth fixture, applies the Laravel migrations, and runs feature tests in transactions. It checks the environment and rejects remote connections.

```powershell
php artisan test
php artisan route:list --except-vendor
npm run build
```

For a disposable **local development** PostgreSQL database, create a separate database and initialize it with `psql -h 127.0.0.1 -U postgres -d campusspace_dev -f database/testing/auth.sql`. Point the local `.env` at it, use `DB_SSLMODE=disable` for this loopback connection, then run the Laravel migrations and optional demo seeder. The Auth fixture is for disposable PostgreSQL instances.

## CloudPanel deployment

The production workflow runs Laravel tests on an isolated PostgreSQL service and builds Vite assets on GitHub Actions. The validated assets are transferred over SSH. The server runs the application through PHP-FPM. See the [deployment runbook](docs/deployment.md) for the existing SSH secret names, production environment, CloudPanel document root, and verification commands.
