begin;

create schema if not exists campus_private;
revoke all on schema campus_private from public, anon, authenticated;
grant usage on schema campus_private to authenticated;

create type public.campus_role as enum ('student', 'admin');
create type public.booking_status as enum ('pending', 'approved', 'rejected', 'cancelled', 'completed');
create type public.equipment_status as enum ('available', 'maintenance', 'inactive');

create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  full_name text not null check (char_length(full_name) between 1 and 100),
  email text not null,
  role public.campus_role not null default 'student',
  created_at timestamptz not null default now()
);

create table public.rooms (
  id uuid primary key default gen_random_uuid(),
  name text not null unique check (char_length(name) between 2 and 100),
  location text not null check (char_length(location) between 2 and 120),
  description text not null check (char_length(description) between 1 and 1500),
  capacity integer not null check (capacity between 1 and 500),
  chairs integer not null check (chairs between 0 and 500),
  tables_count integer not null check (tables_count between 0 and 500),
  active boolean not null default true,
  photo text check (photo in ('seminar', 'classroom', 'hall')),
  layout jsonb not null check (jsonb_typeof(layout) = 'object' and pg_column_size(layout) <= 12000),
  updated_at timestamptz not null default now()
);

create table public.room_facilities (
  room_id uuid not null references public.rooms(id) on delete cascade,
  facility text not null check (facility in ('Projector','Air conditioning','Whiteboard','Audio system','Wi-Fi','Accessible entry')),
  primary key (room_id, facility)
);

create table public.equipment (
  id uuid primary key default gen_random_uuid(),
  name text not null unique check (char_length(name) between 2 and 100),
  category text not null check (category in ('Audio','Presentation','Furniture','Power','Accessories')),
  description text not null check (char_length(description) between 1 and 1500),
  total_quantity integer not null check (total_quantity between 0 and 10000),
  unusable_quantity integer not null default 0 check (unusable_quantity between 0 and total_quantity),
  status public.equipment_status not null default 'available',
  updated_at timestamptz not null default now()
);

create table public.bookings (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete restrict,
  room_id uuid references public.rooms(id) on delete restrict,
  title text not null check (char_length(title) between 2 and 120),
  description text not null check (char_length(description) between 2 and 2000),
  participants integer not null check (participants between 1 and 500),
  committee integer not null check (committee between 0 and 500),
  starts_at timestamptz not null check (isfinite(starts_at)),
  ends_at timestamptz not null check (isfinite(ends_at) and ends_at > starts_at),
  status public.booking_status not null default 'pending',
  planner jsonb check (planner is null or (room_id is not null and jsonb_typeof(planner) = 'object' and pg_column_size(planner) <= 16000)),
  review_note text check (char_length(review_note) <= 1000),
  reviewed_by uuid references public.profiles(id) on delete restrict,
  reviewed_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.booking_equipment (
  booking_id uuid not null references public.bookings(id) on delete cascade,
  equipment_id uuid not null references public.equipment(id) on delete restrict,
  quantity integer not null check (quantity between 1 and 10000),
  primary key (booking_id, equipment_id)
);

create table public.equipment_loans (
  booking_id uuid not null,
  equipment_id uuid not null,
  quantity integer not null check (quantity > 0),
  picked_up_at timestamptz not null default now(),
  picked_up_by uuid not null references public.profiles(id) on delete restrict,
  returned_at timestamptz,
  returned_by uuid references public.profiles(id) on delete restrict,
  missing integer not null default 0 check (missing >= 0),
  damaged integer not null default 0 check (damaged >= 0 and missing + damaged <= quantity),
  return_note text check (char_length(return_note) <= 1000),
  allocation_warning boolean not null default false,
  primary key (booking_id, equipment_id),
  foreign key (booking_id, equipment_id) references public.booking_equipment(booking_id, equipment_id) on delete restrict,
  check ((returned_at is null and returned_by is null and missing = 0 and damaged = 0)
      or (returned_at >= picked_up_at and returned_by is not null))
);

create index bookings_user_time on public.bookings(user_id, starts_at);
create index bookings_status_time on public.bookings(status, starts_at);
create index bookings_room_time on public.bookings(room_id, starts_at, ends_at) where status in ('approved','completed');
create index booking_equipment_item on public.booking_equipment(equipment_id, booking_id);
create index equipment_loans_open on public.equipment_loans(equipment_id) where returned_at is null;

create function campus_private.sync_profile() returns trigger
language plpgsql security definer set search_path = '' as $$
begin
  insert into public.profiles(id, full_name, email)
  values (new.id, coalesce(nullif(left(btrim(new.raw_user_meta_data->>'full_name'),100),''), nullif(split_part(new.email,'@',1),''), 'Campus member'), coalesce(new.email,''))
  on conflict (id) do update set email = excluded.email;
  return new;
end;
$$;
revoke all on function campus_private.sync_profile() from public, anon, authenticated;
create trigger campus_profile_after_auth after insert or update of email on auth.users
for each row execute function campus_private.sync_profile();
insert into public.profiles(id, full_name, email)
select id, coalesce(nullif(left(btrim(raw_user_meta_data->>'full_name'),100),''), nullif(split_part(email,'@',1),''), 'Campus member'), coalesce(email,'')
from auth.users on conflict do nothing;

create function campus_private.is_admin() returns boolean
language sql stable security definer set search_path = '' as $$
  select exists(select 1 from public.profiles where id = (select auth.uid()) and role = 'admin');
$$;
revoke all on function campus_private.is_admin() from public, anon, authenticated;
grant execute on function campus_private.is_admin() to authenticated;

alter table public.profiles enable row level security;
alter table public.rooms enable row level security;
alter table public.room_facilities enable row level security;
alter table public.equipment enable row level security;
alter table public.bookings enable row level security;
alter table public.booking_equipment enable row level security;
alter table public.equipment_loans enable row level security;

revoke all on public.profiles, public.rooms, public.room_facilities, public.equipment, public.bookings, public.booking_equipment, public.equipment_loans from public, anon, authenticated;
grant select on public.profiles, public.rooms, public.room_facilities, public.equipment, public.bookings, public.booking_equipment, public.equipment_loans to authenticated;
create policy read_profiles on public.profiles for select to authenticated using (id = (select auth.uid()) or (select campus_private.is_admin()));
create policy read_rooms on public.rooms for select to authenticated using (true);
create policy read_facilities on public.room_facilities for select to authenticated using (true);
create policy read_equipment on public.equipment for select to authenticated using (true);
create policy read_bookings on public.bookings for select to authenticated using (user_id = (select auth.uid()) or (select campus_private.is_admin()));
create policy read_booking_items on public.booking_equipment for select to authenticated using (
  exists(select 1 from public.bookings where id = booking_id)
);
create policy read_loans on public.equipment_loans for select to authenticated using (
  exists(select 1 from public.bookings where id = booking_id)
);

-- PostgREST embeds loan records in bookings using this read-only relationship.
create function public.equipment_loans(public.bookings) returns setof public.equipment_loans
language sql stable security invoker set search_path = '' as $$
  select loan.* from public.equipment_loans loan where loan.booking_id = $1.id;
$$;
revoke all on function public.equipment_loans(public.bookings) from public, anon, authenticated;
grant execute on function public.equipment_loans(public.bookings) to authenticated;

create function campus_private.room_free(p_room uuid, p_start timestamptz, p_end timestamptz) returns boolean
language sql stable set search_path = '' as $$
  select not exists (
    select 1 from public.bookings b where b.room_id = p_room and b.status in ('approved','completed')
      and b.starts_at < p_end and b.ends_at > p_start
  );
$$;

-- Demand can change only at an interval boundary. Use the maximum concurrent
-- demand rather than the sum of bookings that merely intersect the query.
create function campus_private.equipment_peak(p_item uuid, p_start timestamptz, p_end timestamptz) returns integer
language sql stable set search_path = '' as $$
  with allocations as (
    select b.starts_at as start_at,
      case when l.picked_up_at is not null and l.returned_at is null and b.ends_at <= now()
        then 'infinity'::timestamptz else b.ends_at end as end_at, be.quantity
    from public.booking_equipment be join public.bookings b on b.id = be.booking_id
    left join public.equipment_loans l on l.booking_id = b.id and l.equipment_id = be.equipment_id
    where be.equipment_id = p_item and b.status = 'approved' and l.returned_at is null
  ), relevant as (
    select * from allocations where start_at < p_end and end_at > p_start
  ), events as (
    select greatest(start_at,p_start) as at, quantity as delta from relevant
    union all select least(end_at,p_end), -quantity from relevant
  ), demand as (
    select sum(sum(delta)) over (order by at) as quantity from events group by at
  )
  select coalesce(max(quantity),0)::integer from demand;
$$;
revoke all on function campus_private.room_free(uuid,timestamptz,timestamptz), campus_private.equipment_peak(uuid,timestamptz,timestamptz) from public, anon, authenticated;

create function public.resource_availability(p_start timestamptz, p_end timestamptz) returns jsonb
language plpgsql stable security definer set search_path = '' as $$
begin
  if auth.uid() is null then raise exception 'app_auth'; end if;
  if p_start is null or p_end is null or not isfinite(p_start) or not isfinite(p_end) or p_end <= p_start then raise exception 'app_dates'; end if;
  return jsonb_build_object(
    'rooms', coalesce((select jsonb_object_agg(r.id, r.active and campus_private.room_free(r.id,p_start,p_end)) from public.rooms r),'{}'::jsonb),
    'equipment', coalesce((select jsonb_object_agg(e.id, case when e.status = 'available' then
      greatest(0,e.total_quantity-e.unusable_quantity-campus_private.equipment_peak(e.id,p_start,p_end)) else 0 end) from public.equipment e),'{}'::jsonb)
  );
end;
$$;

create function public.room_schedule(p_room uuid, p_start timestamptz, p_end timestamptz)
returns table(starts_at timestamptz, ends_at timestamptz)
language plpgsql stable security definer set search_path = '' as $$
begin
  if auth.uid() is null then raise exception 'app_auth'; end if;
  if p_start is null or p_end is null or not isfinite(p_start) or not isfinite(p_end) or p_end <= p_start then raise exception 'app_dates'; end if;
  if p_end-p_start > interval '31 days' then raise exception 'app_dates'; end if;
  return query select b.starts_at, b.ends_at from public.bookings b
  where b.room_id = p_room and b.status in ('approved','completed') and b.starts_at < p_end and b.ends_at > p_start order by b.starts_at;
end;
$$;

create function campus_private.check_booking(p_booking uuid) returns void
language plpgsql set search_path = '' as $$
declare
  b public.bookings%rowtype;
  r public.rooms%rowtype;
  item record;
begin
  select * into strict b from public.bookings where id = p_booking;
  if b.room_id is not null then
    select * into r from public.rooms where id = b.room_id;
    if not found or not r.active then raise exception 'app_room_unavailable'; end if;
    if b.participants + b.committee > r.capacity then raise exception 'app_capacity'; end if;
    if not campus_private.room_free(r.id,b.starts_at,b.ends_at) then raise exception 'app_room_conflict'; end if;
    if b.planner is not null and ((b.planner->'roomLayout') is distinct from r.layout
      or (b.planner->>'chairs')::integer > r.chairs or (b.planner->>'tables')::integer > r.tables_count) then
      raise exception 'app_plan_changed';
    end if;
  end if;
  for item in select be.quantity,e.* from public.booking_equipment be
    join public.equipment e on e.id = be.equipment_id where be.booking_id = b.id
  loop
    if item.status <> 'available' or item.quantity + campus_private.equipment_peak(item.id,b.starts_at,b.ends_at)
      > item.total_quantity - item.unusable_quantity then raise exception 'app_equipment_conflict'; end if;
  end loop;
end;
$$;
revoke all on function campus_private.check_booking(uuid) from public, anon, authenticated;

create function public.create_booking(
  p_room uuid, p_title text, p_description text, p_participants integer, p_committee integer,
  p_start timestamptz, p_end timestamptz, p_items jsonb, p_planner jsonb default null
) returns uuid language plpgsql security definer set search_path = '' as $$
declare booking_id uuid;
begin
  if auth.uid() is null then raise exception 'app_auth'; end if;
  if p_start is null or p_end is null or not isfinite(p_start) or not isfinite(p_end) or p_start <= now() or p_end <= p_start then raise exception 'app_dates'; end if;
  if p_items is null or jsonb_typeof(p_items) <> 'array' then raise exception 'app_items'; end if;
  if jsonb_array_length(p_items) > 100 or (p_room is null and jsonb_array_length(p_items)=0) then raise exception 'app_items'; end if;
  if p_planner is not null and (p_room is null or (p_planner->>'participants') is distinct from p_participants::text
    or (p_planner->>'committee') is distinct from p_committee::text
    or jsonb_typeof(p_planner->'roomLayout') is distinct from 'object'
    or coalesce(p_planner->>'chairs','') !~ '^[0-9]{1,3}$'
    or coalesce(p_planner->>'tables','') !~ '^[0-9]{1,3}$') then raise exception 'app_plan'; end if;
  perform pg_advisory_xact_lock(832150425::bigint);
  insert into public.bookings(user_id,room_id,title,description,participants,committee,starts_at,ends_at,planner)
    values(auth.uid(),p_room,btrim(p_title),btrim(p_description),p_participants,p_committee,p_start,p_end,p_planner)
    returning id into booking_id;
  insert into public.booking_equipment(booking_id,equipment_id,quantity)
    select booking_id,i.equipment_id,i.quantity from jsonb_to_recordset(p_items) as i(equipment_id uuid, quantity integer);
  perform campus_private.check_booking(booking_id);
  return booking_id;
end;
$$;

create function public.cancel_booking(p_booking uuid) returns void
language plpgsql security definer set search_path = '' as $$
begin
  if auth.uid() is null then raise exception 'app_auth'; end if;
  perform pg_advisory_xact_lock(832150425::bigint);
  update public.bookings set status='cancelled', updated_at=now() where id=p_booking and user_id=auth.uid() and status='pending';
  if not found then raise exception 'app_state'; end if;
end;
$$;

create function public.review_booking(p_booking uuid, p_approve boolean, p_reason text default null) returns void
language plpgsql security definer set search_path = '' as $$
declare b public.bookings%rowtype;
begin
  if not campus_private.is_admin() then raise exception 'app_admin'; end if;
  perform pg_advisory_xact_lock(832150425::bigint);
  select * into b from public.bookings where id=p_booking and status='pending';
  if not found or p_approve is null then raise exception 'app_state'; end if;
  if p_approve then
    if b.starts_at <= now() then raise exception 'app_dates'; end if;
    perform campus_private.check_booking(p_booking);
  elsif coalesce(char_length(btrim(p_reason)),0) not between 3 and 1000 then
    raise exception 'app_reason';
  end if;
  update public.bookings set status=case when p_approve then 'approved'::public.booking_status else 'rejected'::public.booking_status end,
    review_note=case when p_approve then null else btrim(p_reason) end, reviewed_by=auth.uid(), reviewed_at=now(), updated_at=now()
    where id=p_booking;
end;
$$;

create function public.save_room(p_id uuid, p_name text, p_location text, p_description text,
  p_capacity integer, p_chairs integer, p_tables integer, p_active boolean, p_photo text, p_layout jsonb, p_facilities text[])
returns uuid language plpgsql security definer set search_path = '' as $$
declare r public.rooms%rowtype;
begin
  if not campus_private.is_admin() then raise exception 'app_admin'; end if;
  perform pg_advisory_xact_lock(832150425::bigint);
  if p_id is null then
    insert into public.rooms(name,location,description,capacity,chairs,tables_count,active,photo,layout)
    values(btrim(p_name),btrim(p_location),btrim(p_description),p_capacity,p_chairs,p_tables,p_active,p_photo,p_layout)
    returning id into p_id;
  else
    select * into r from public.rooms where id=p_id;
    if not found then raise exception 'app_state'; end if;
    if exists(select 1 from public.bookings b where b.room_id=p_id and b.status in ('approved','completed')
      and b.ends_at>now() and (not p_active or b.participants+b.committee>p_capacity
        or (b.planner is not null and (p_layout is distinct from r.layout or p_chairs<r.chairs or p_tables<r.tables_count))))
      or (exists(select 1 from public.bookings b where b.room_id=p_id and b.status='approved' and b.ends_at>now())
        and exists(select 1 from public.room_facilities f where f.room_id=p_id and not (f.facility = any(coalesce(p_facilities,'{}'::text[]))))) then
      raise exception 'app_room_allocated';
    end if;
    update public.rooms set name=btrim(p_name),location=btrim(p_location),description=btrim(p_description),capacity=p_capacity,
      chairs=p_chairs,tables_count=p_tables,active=p_active,photo=p_photo,layout=p_layout,updated_at=now() where id=p_id;
  end if;
  delete from public.room_facilities where room_id=p_id;
  insert into public.room_facilities(room_id,facility) select distinct p_id,f from unnest(p_facilities) f;
  return p_id;
end;
$$;

create function public.save_equipment(p_id uuid, p_name text, p_category text, p_description text, p_total integer, p_unusable integer, p_status public.equipment_status)
returns uuid language plpgsql security definer set search_path = '' as $$
begin
  if not campus_private.is_admin() then raise exception 'app_admin'; end if;
  perform pg_advisory_xact_lock(832150425::bigint);
  if p_id is null then
    insert into public.equipment(name,category,description,total_quantity,unusable_quantity,status)
      values(btrim(p_name),p_category,btrim(p_description),p_total,p_unusable,p_status) returning id into p_id;
  else
    if campus_private.equipment_peak(p_id,now(),'infinity') > (case when p_status='available' then p_total-p_unusable else 0 end) then
      raise exception 'app_equipment_allocated';
    end if;
    update public.equipment set name=btrim(p_name),category=p_category,description=btrim(p_description),
      total_quantity=p_total,unusable_quantity=p_unusable,status=p_status,updated_at=now() where id=p_id;
    if not found then raise exception 'app_state'; end if;
  end if;
  return p_id;
end;
$$;

create function public.record_pickup(p_booking uuid) returns void
language plpgsql security definer set search_path = '' as $$
declare b public.bookings%rowtype;
begin
  if not campus_private.is_admin() then raise exception 'app_admin'; end if;
  perform pg_advisory_xact_lock(832150425::bigint);
  select * into b from public.bookings where id=p_booking and status='approved';
  if not found or exists(select 1 from public.equipment_loans where booking_id=p_booking) then raise exception 'app_state'; end if;
  if now()<b.starts_at or now()>=b.ends_at then raise exception 'app_pickup_time'; end if;
  if exists(select 1 from public.booking_equipment be join public.equipment e on e.id=be.equipment_id
    where be.booking_id=p_booking and (e.status<>'available' or be.quantity + coalesce((
      select sum(l.quantity) from public.equipment_loans l where l.equipment_id=e.id and l.returned_at is null
    ),0) > e.total_quantity-e.unusable_quantity)) then raise exception 'app_equipment_conflict'; end if;
  insert into public.equipment_loans(booking_id,equipment_id,quantity,picked_up_by)
    select p_booking,be.equipment_id,be.quantity,auth.uid() from public.booking_equipment be where be.booking_id=p_booking;
  if not found then raise exception 'app_items'; end if;
end;
$$;

create function public.record_return(p_booking uuid,p_items jsonb,p_note text default null) returns boolean
language plpgsql security definer set search_path = '' as $$
declare
  item record;
  item_count integer;
  has_warning boolean := false;
  item_warning boolean;
begin
  if not campus_private.is_admin() then raise exception 'app_admin'; end if;
  perform pg_advisory_xact_lock(832150425::bigint);
  if p_items is null or jsonb_typeof(p_items)<>'array' then raise exception 'app_items'; end if;
  select count(*) into item_count from public.equipment_loans where booking_id=p_booking and returned_at is null;
  if item_count=0 or jsonb_array_length(p_items)<>item_count then raise exception 'app_state'; end if;
  if exists(select 1 from jsonb_to_recordset(p_items) x(equipment_id uuid,missing integer,damaged integer)
    left join public.equipment_loans l on l.booking_id=p_booking and l.equipment_id=x.equipment_id and l.returned_at is null
    where l.equipment_id is null or x.missing is null or x.damaged is null or x.missing<0 or x.damaged<0
      or x.missing::bigint+x.damaged>l.quantity)
    or (select count(distinct x.equipment_id) from jsonb_to_recordset(p_items) x(equipment_id uuid))<>item_count
    then raise exception 'app_return_counts'; end if;
  update public.equipment_loans l set returned_at=now(),returned_by=auth.uid(),missing=x.missing,damaged=x.damaged,
    return_note=nullif(btrim(p_note),'')
  from jsonb_to_recordset(p_items) x(equipment_id uuid,missing integer,damaged integer)
  where l.booking_id=p_booking and l.equipment_id=x.equipment_id;
  update public.bookings set status='completed',updated_at=now() where id=p_booking and status='approved';
  if not found then raise exception 'app_state'; end if;
  for item in select * from jsonb_to_recordset(p_items) x(equipment_id uuid,missing integer,damaged integer)
    where x.missing+x.damaged>0
  loop
    update public.equipment set unusable_quantity=unusable_quantity+item.missing+item.damaged,updated_at=now() where id=item.equipment_id
    returning (campus_private.equipment_peak(id,now(),'infinity') > total_quantity-unusable_quantity) into item_warning;
    if item_warning then
      update public.equipment_loans set allocation_warning=true where booking_id=p_booking and equipment_id=item.equipment_id;
      has_warning := true;
    end if;
  end loop;
  return has_warning;
end;
$$;

create function public.complete_room_booking(p_booking uuid) returns void
language plpgsql security definer set search_path = '' as $$
begin
  if not campus_private.is_admin() then raise exception 'app_admin'; end if;
  perform pg_advisory_xact_lock(832150425::bigint);
  update public.bookings b set status='completed',updated_at=now()
  where b.id=p_booking and b.status='approved' and b.ends_at<=now()
    and not exists(select 1 from public.booking_equipment be where be.booking_id=b.id);
  if not found then raise exception 'app_state'; end if;
end;
$$;

revoke all on function public.resource_availability(timestamptz,timestamptz),public.room_schedule(uuid,timestamptz,timestamptz),
  public.create_booking(uuid,text,text,integer,integer,timestamptz,timestamptz,jsonb,jsonb),public.cancel_booking(uuid),
  public.review_booking(uuid,boolean,text),public.save_room(uuid,text,text,text,integer,integer,integer,boolean,text,jsonb,text[]),
  public.save_equipment(uuid,text,text,text,integer,integer,public.equipment_status),public.record_pickup(uuid),
  public.record_return(uuid,jsonb,text),public.complete_room_booking(uuid) from public,anon,authenticated;
grant execute on function public.resource_availability(timestamptz,timestamptz),public.room_schedule(uuid,timestamptz,timestamptz),
  public.create_booking(uuid,text,text,integer,integer,timestamptz,timestamptz,jsonb,jsonb),public.cancel_booking(uuid),
  public.review_booking(uuid,boolean,text),public.save_room(uuid,text,text,text,integer,integer,integer,boolean,text,jsonb,text[]),
  public.save_equipment(uuid,text,text,text,integer,integer,public.equipment_status),public.record_pickup(uuid),
  public.record_return(uuid,jsonb,text),public.complete_room_booking(uuid) to authenticated;

commit;
