<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('CampusSpace requires a PostgreSQL connection.');
        }

        $tables = [
            'profiles' => ['id', 'full_name', 'email', 'role', 'created_at'],
            'rooms' => ['id', 'name', 'location', 'description', 'capacity', 'chairs', 'tables_count', 'active', 'photo', 'layout', 'updated_at'],
            'room_facilities' => ['room_id', 'facility'],
            'equipment' => ['id', 'name', 'category', 'description', 'total_quantity', 'unusable_quantity', 'status', 'updated_at'],
            'bookings' => ['id', 'user_id', 'room_id', 'title', 'description', 'participants', 'committee', 'starts_at', 'ends_at', 'status', 'planner', 'review_note', 'reviewed_by', 'reviewed_at', 'created_at', 'updated_at'],
            'booking_equipment' => ['booking_id', 'equipment_id', 'quantity'],
            'equipment_loans' => ['booking_id', 'equipment_id', 'quantity', 'picked_up_at', 'picked_up_by', 'returned_at', 'returned_by', 'missing', 'damaged', 'return_note', 'allocation_warning'],
        ];

        // Catalog reads must reflect the real schema, including during --pretend.
        $installed = $connection->withoutPretending(
            fn () => array_filter(array_keys($tables), fn (string $table) => Schema::hasTable('public.'.$table)),
        );

        if ($installed === []) {
            $connection->withoutPretending(fn () => $this->checkInstallPrivileges());
            $baseline = File::get(base_path('supabase/migrations/20261111000100_campusspace.sql'));

            // Laravel owns the surrounding migration transaction.
            DB::statement("set local lock_timeout = '5s'");
            DB::unprepared(preg_replace('/\A\s*begin;\s*|\s*commit;\s*\z/i', '', $baseline));

            if ($connection->pretending()) {
                return;
            }
        }

        $connection->withoutPretending(fn () => $this->checkInstalledSchema($tables));
    }

    private function checkInstallPrivileges(): void
    {
        $platform = DB::selectOne(<<<'SQL'
            select
                has_database_privilege(current_database(), 'CREATE') as create_schema,
                has_schema_privilege('public', 'USAGE') and has_schema_privilege('public', 'CREATE') as use_public,
                has_schema_privilege(to_regnamespace('auth'), 'USAGE') as use_auth,
                (select count(*) = 3 from pg_catalog.pg_roles where rolname in ('anon', 'authenticated', 'service_role')) as api_roles
            SQL);

        if (! $platform->create_schema || ! $platform->use_public) {
            throw new RuntimeException('The migration role needs CREATE on the database and USAGE and CREATE on the public schema.');
        }
        if (! $platform->use_auth || ! $platform->api_roles) {
            throw new RuntimeException('The migration role needs access to Supabase Auth and the Supabase API roles must exist. Use the isolated Auth fixture for local tests.');
        }

        $auth = DB::selectOne(<<<'SQL'
            select
                has_function_privilege(to_regprocedure('auth.uid()'), 'EXECUTE') as execute_uid,
                has_table_privilege(to_regclass('auth.users'), 'TRIGGER') as create_trigger,
                has_column_privilege(to_regclass('auth.users'), 'id', 'REFERENCES') as reference_id,
                has_column_privilege(to_regclass('auth.users'), 'id', 'SELECT')
                    and has_column_privilege(to_regclass('auth.users'), 'email', 'SELECT')
                    and has_column_privilege(to_regclass('auth.users'), 'raw_user_meta_data', 'SELECT') as backfill_profiles
            SQL);

        if (! $auth->execute_uid || ! $auth->create_trigger || ! $auth->reference_id || ! $auth->backfill_profiles) {
            throw new RuntimeException('The migration role needs EXECUTE on auth.uid(), TRIGGER on auth.users, REFERENCES on auth.users.id and SELECT on the profile backfill columns.');
        }
    }

    private function checkInstalledSchema(array $tables): void
    {
        foreach ($tables as $table => $columns) {
            $qualifiedTable = 'public.'.$table;
            if (! Schema::hasColumns($qualifiedTable, $columns)) {
                throw new RuntimeException("The existing CampusSpace schema is incomplete at {$qualifiedTable}. Resolve the schema mismatch before deploying.");
            }
            if (! DB::scalar('select relrowsecurity from pg_catalog.pg_class where oid = to_regclass(?)', [$qualifiedTable])) {
                throw new RuntimeException("Enable row-level security on {$qualifiedTable} before adopting the CampusSpace schema.");
            }
        }

        $functions = [
            'public.resource_availability(timestamptz,timestamptz)',
            'public.room_schedule(uuid,timestamptz,timestamptz)',
            'public.create_booking(uuid,text,text,integer,integer,timestamptz,timestamptz,jsonb,jsonb)',
            'public.cancel_booking(uuid)',
            'public.review_booking(uuid,boolean,text)',
            'public.save_room(uuid,text,text,text,integer,integer,integer,boolean,text,jsonb,text[])',
            'public.save_equipment(uuid,text,text,text,integer,integer,public.equipment_status)',
            'public.record_pickup(uuid)',
            'public.record_return(uuid,jsonb,text)',
            'public.complete_room_booking(uuid)',
        ];

        foreach ($functions as $signature) {
            if (! DB::scalar('select to_regprocedure(?) is not null', [$signature])) {
                throw new RuntimeException("The CampusSpace schema is missing {$signature}.");
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('The CampusSpace baseline may contain existing Supabase data. Roll back application code separately.');
    }
};
