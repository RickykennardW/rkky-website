<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("set local lock_timeout = '5s'");
        DB::unprepared(<<<'SQL'
            alter table public.bookings add column required_facilities jsonb not null default '[]'::jsonb
                check (jsonb_typeof(required_facilities) = 'array' and jsonb_array_length(required_facilities) <= 6);

            create function campus_private.require_booking_facilities() returns trigger
            language plpgsql set search_path = '' as $$
            begin
                if new.status in ('pending', 'approved') and exists (
                    select 1 from jsonb_array_elements_text(new.required_facilities) as item(name)
                    where not exists (
                        select 1 from public.room_facilities f where f.room_id = new.room_id and f.facility = item.name
                    )
                ) then
                    raise exception 'app_facilities';
                end if;
                return new;
            end;
            $$;
            revoke all on function campus_private.require_booking_facilities() from public, anon, authenticated, service_role;

            create trigger booking_required_facilities before insert or update of status, room_id, required_facilities
                on public.bookings for each row execute function campus_private.require_booking_facilities();

            create or replace function campus_private.room_free(p_room uuid, p_start timestamptz, p_end timestamptz) returns boolean
            language sql stable set search_path = '' as $$
                select not exists (
                    select 1 from public.bookings b
                    where b.room_id = p_room and b.status = 'approved'
                        and p_start < b.ends_at and p_end > b.starts_at
                );
            $$;

            create or replace function public.room_schedule(p_room uuid, p_start timestamptz, p_end timestamptz)
            returns table(starts_at timestamptz, ends_at timestamptz)
            language plpgsql stable security definer set search_path = '' as $$
            begin
                if auth.uid() is null then raise exception 'app_auth'; end if;
                if p_start is null or p_end is null or not isfinite(p_start) or not isfinite(p_end)
                    or p_end <= p_start or p_end-p_start > interval '31 days' then raise exception 'app_dates'; end if;
                return query select b.starts_at, b.ends_at from public.bookings b
                where b.room_id = p_room and (b.status = 'approved' or (b.status = 'completed' and b.ends_at <= now()))
                    and b.starts_at < p_end and b.ends_at > p_start order by b.starts_at;
            end;
            $$;
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('This forward-only migration preserves existing booking requirements.');
    }
};
