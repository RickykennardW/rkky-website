<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("set local lock_timeout = '5s'");

        // Keep profile IDs and booking references while allowing native Laravel accounts.
        DB::unprepared(<<<'SQL'
            do $$
            declare
                legacy_fk name;
            begin
                for legacy_fk in
                    select c.conname from pg_catalog.pg_constraint c
                    join pg_catalog.pg_attribute profile_id on profile_id.attrelid = c.conrelid and profile_id.attname = 'id'
                    join pg_catalog.pg_attribute auth_id on auth_id.attrelid = c.confrelid and auth_id.attname = 'id'
                    where c.contype = 'f'
                        and c.conrelid = 'public.profiles'::regclass and c.confrelid = 'auth.users'::regclass
                        and c.conkey = array[profile_id.attnum] and c.confkey = array[auth_id.attnum]
                loop
                    execute format('alter table public.profiles drop constraint %I', legacy_fk);
                end loop;
            end;
            $$;
            SQL);

        Schema::create('campus_private.users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->timestampTz('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
            $table->foreign('id')->references('id')->on('public.profiles')->restrictOnDelete();
        });

        Schema::create('campus_private.notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->text('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
            $table->foreign('notifiable_id')->references('id')->on('campus_private.users')->cascadeOnDelete();
        });

        DB::statement('revoke all on schema campus_private from public, anon, service_role');
        DB::statement('revoke all on campus_private.users, campus_private.notifications from public, anon, authenticated, service_role');
        DB::statement('alter table campus_private.users enable row level security');
        DB::statement('alter table campus_private.notifications enable row level security');
    }

    public function down(): void
    {
        throw new RuntimeException('Removing Laravel accounts and notifications would erase application data.');
    }
};
