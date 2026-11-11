# CampusSpace

CampusSpace is a campus room and equipment reservation app. Students can check availability, submit requests, follow their status, and attach a room plan. Campus staff can manage the catalog, approve requests, and record equipment pickup, returns, and losses.

## Run locally

Use Node.js 22.12 or later and npm. From the project root, run:

```sh
npm ci
npm run dev
```

Open [localhost:3000](http://localhost:3000). The dashboard, directories, and planner show a labeled fictional catalog when Supabase is unconfigured. Accounts, live availability, bookings, and administration require a Supabase project.

## Connect Supabase

1. Open your CampusSpace project in Supabase. If the schema has not been applied, run [the CampusSpace migration](supabase/migrations/20261111000100_campusspace.sql) once in its SQL Editor.
2. To add fictional rooms and equipment for testing, run [the optional seed](supabase/seed.sql) in the SQL Editor. Create your test accounts through the normal sign-up flow.
3. Edit `.env.local` at the project root. If the file is missing, copy [.env.example](.env.example) first. Copy the Project URL from the Supabase **Connect** dialog and use the publishable key from the same project. Restart Next.js after changing the file.

| Variable | Value |
| --- | --- |
| `NEXT_PUBLIC_SUPABASE_URL` | Supabase project URL |
| `NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY` | Publishable key from the Supabase project |
| `NEXT_PUBLIC_SITE_URL` | App origin for Auth redirects. Defaults to `http://localhost:3000` locally. Set it to your production origin when deploying. |

Use the publishable key for the app. Keep any Supabase secret or service-role key out of the environment variables above, browser code, and Git.

4. In Supabase Authentication settings, enable the Email provider and set the **Site URL** to your app origin. Add `http://localhost:3000/auth/confirm` and your production `/auth/confirm` URL to the redirect allowlist, adjusting the port for your local server.
5. With email confirmation enabled, update the link in the **Confirm signup** email template to the following URL. Configure email delivery for your users in Supabase.

```text
{{ .RedirectTo }}?token_hash={{ .TokenHash }}
```

The registration form supplies the full allowlisted `/auth/confirm` URL. See [Supabase’s Next.js Auth setup](https://supabase.com/docs/guides/auth/server-side/creating-a-client?environment=route-handler&framework=nextjs).

### Create the first admin

Register the intended staff member through the application and verify the account. In the Supabase dashboard, open **Authentication → Users** and copy that account’s UUID. In the project SQL Editor, replace the example UUID below and verify the returned account and role.

```sql
update public.profiles
set role = 'admin'
where id = '00000000-0000-0000-0000-000000000000'
returning id, email, role;
```

The account can then open **Administration** from the sidebar. Sign-up creates a student profile. Profile roles can be assigned through the database by an authorized project operator.

## Booking and inventory rules

- The UI displays campus time in WIB (UTC+7). Booking timestamps are stored as `timestamptz`.
- Pending requests enter the review queue. Approved reservations hold the room and the required equipment for the booking period.
- Approval checks room overlap and peak concurrent equipment demand inside the same transaction. The database serializes allocation-changing actions.
- Equipment pickup rechecks physical stock. Overdue units remain unavailable until return. A return records any missing or damaged units and flags an allocation shortfall for staff review.
- The SVG planner uses room geometry, fixed furniture, blocked zones, and clearance rules. Its estimates can be adjusted and attached to a booking. The result is a planning aid. Staff should verify the actual arrangement and required clearances before use.

## Verify

```sh
npm run lint
npm run typecheck
npm test
npm run build
```

The test suite runs the migration and seed in an isolated PostgreSQL instance through PGlite, mocks Supabase Auth in server-action tests, and tests the planner independently.

To verify the hosted integration after setup, register and confirm a student account, sign in, and open **Rooms** to check that the catalog loads from Supabase. Submit a booking, sign in with the assigned admin account to review it, then verify the updated status from the student account.

## Deploy to Vercel

1. Import this repository as a Next.js project with a supported Node.js version.
2. Configure the three environment variables before building. Set `NEXT_PUBLIC_SITE_URL` to the deployed origin.
3. Apply the migration to the production Supabase project and configure the Auth URLs and email template as above.
4. Deploy. Register a student account and verify email confirmation, then verify staff access and approve a test reservation.

For a self-hosted Next.js server, run `npm run build` followed by `npm start` with the same environment variables.
