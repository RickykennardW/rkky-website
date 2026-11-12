@props(['account' => null, 'mobile' => false])
@php
    $workspace = [
        ['dashboard', 'Dashboard', 'layout-dashboard', ['dashboard']],
        ['rooms.index', 'Rooms', 'building-2', ['rooms.*']],
        ['equipment.index', 'Equipment', 'boxes', ['equipment.*']],
        ['bookings.index', 'My Bookings', 'calendar-days', ['bookings.*']],
        ['planner.show', 'Room Planner', 'grid-2x2', ['planner.*']],
    ];
    $admin = [
        ['admin.dashboard', 'Admin Overview', 'shield-check', ['admin.dashboard']],
        ['admin.bookings.index', 'Booking Requests', 'calendar-check', ['admin.bookings.*']],
        ['admin.rooms.index', 'Manage Rooms', 'building-2', ['admin.rooms.*']],
        ['admin.equipment.index', 'Manage Equipment', 'settings-2', ['admin.equipment.*']],
        ['admin.loans.index', 'Equipment Loans', 'package-check', ['admin.loans.*']],
    ];
    $groups = ['Workspace' => $workspace];
    if ($account?->isAdmin()) {
        $groups['Administration'] = config('campusspace.demo') ? array_slice($admin, 0, 2) : $admin;
    }
@endphp
<div class="flex h-full flex-col">
    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-5">
        <x-brand />
        @if($mobile)
            <button type="button" x-on:click="$refs.navigation.close()" class="button button-ghost px-2" aria-label="Close navigation"><x-icon name="x" /></button>
        @endif
    </div>
    <nav aria-label="Workspace" class="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto px-3 py-5">
        @foreach($groups as $label => $links)
            <div class="grid gap-1">
                <p class="mb-1 px-3 text-[0.65rem] font-semibold tracking-[0.16em] text-muted uppercase">{{ $label }}</p>
                @foreach($links as [$name, $text, $icon, $patterns])
                    @php($active = request()->routeIs(...$patterns))
                    <a href="{{ route($name) }}" @if($active) aria-current="page" @endif
                       @class(['flex min-h-11 items-center gap-3 rounded-lg px-3 text-[0.84rem] font-medium transition-colors', 'bg-[#1b2c45] text-[#aec8fc]' => $active, 'text-muted hover:bg-raised hover:text-ink' => !$active])>
                        <x-icon :name="$icon" class="size-[17px]" />{{ $text }}
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>
    <div class="grid gap-3 border-t border-line p-3">
        @if($account)
            <div class="flex items-center gap-2.5 rounded-lg bg-raised p-2.5">
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-[#243755] text-xs font-semibold text-[#c3d6fb]" aria-hidden="true">{{ mb_strtoupper(mb_substr($account->profile->full_name, 0, 2)) }}</span>
                <div class="min-w-0 flex-1"><div class="truncate text-xs font-medium">{{ $account->profile->full_name }}</div><div class="mt-0.5 text-xs text-muted capitalize">{{ $account->profile->role }}</div></div>
                <form method="post" action="{{ route('logout') }}">@csrf
                    <button type="submit" aria-label="Sign out" title="Sign out" class="rounded p-2 text-muted hover:bg-[#283748] hover:text-ink"><x-icon name="log-out" class="size-4" /></button>
                </form>
            </div>
        @else
            <a href="{{ route(config('campusspace.demo') ? 'login' : 'register') }}" class="button button-secondary w-full">{{ config('campusspace.demo') ? 'Choose demo role' : 'Create an account' }} <x-icon name="arrow-up-right" class="size-4" /></a>
        @endif
    </div>
</div>
