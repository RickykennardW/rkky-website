@props(['account' => null, 'mobile' => false])
@php
    $workspace = [
        ['dashboard', 'Beranda', 'layout-dashboard', ['dashboard']],
        ['rooms.index', 'Cari Ruangan', 'building-2', ['rooms.*', 'scheduling.*', 'planner.*', 'bookings.create']],
        ['bookings.index', 'Peminjaman Saya', 'calendar-days', ['bookings.index', 'bookings.show']],
        ['equipment.index', 'Peralatan', 'boxes', ['equipment.*']],
    ];
    $admin = [
        ['admin.dashboard', 'Beranda Admin', 'shield-check', ['admin.dashboard']],
        ['admin.bookings.index', 'Permintaan', 'calendar-check', ['admin.bookings.*']],
        ['monitoring.index', 'Pantau Ruangan', 'clock-3', ['monitoring.*']],
        ['admin.rooms.index', 'Kelola Ruangan', 'building-2', ['admin.rooms.*']],
        ['admin.equipment.index', 'Kelola Peralatan', 'settings-2', ['admin.equipment.*']],
        ['admin.loans.index', 'Pengembalian', 'package-check', ['admin.loans.*']],
    ];
    $groups = ['Layanan Kampus' => $account ? $workspace : array_slice($workspace, 0, 1)];
    if ($account?->isAdmin()) {
        $groups = ['Administrasi' => config('campusspace.demo') ? array_slice($admin, 0, 3) : $admin, ...$groups];
    }
@endphp
<div class="flex h-full flex-col">
    <div class="flex min-h-17 items-center justify-between gap-2 border-b border-line px-4 lg:px-5">
        <x-brand />
        @if($mobile)
            <button type="button" x-on:click="$refs.navigation.close()" class="button button-ghost px-2.5" aria-label="Tutup menu"><x-icon name="x" class="size-5" /></button>
        @endif
    </div>
    <nav aria-label="Menu utama" class="flex min-h-0 flex-1 flex-col gap-7 overflow-y-auto p-3 pt-7">
        @foreach($groups as $label => $links)
            <div class="grid gap-1">
                <p class="mb-2 px-3 text-xs font-semibold text-muted">{{ $label }}</p>
                @foreach($links as [$name, $text, $icon, $patterns])
                    @php($active = request()->routeIs(...$patterns))
                    <a href="{{ route($name) }}" @if($active) aria-current="page" @endif
                       @class(['flex min-h-11 items-center gap-2.5 rounded-lg px-3 text-sm font-medium transition-colors', 'bg-brand/15 text-accent ring-1 ring-inset ring-brand/20' => $active, 'text-muted hover:bg-raised hover:text-ink' => !$active])>
                        <x-icon :name="$icon" class="size-[18px]" />{{ $text }}
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>
    <div class="grid gap-3 border-t border-line p-3">
        @if($account)
            <div class="flex items-center gap-2.5 rounded-lg p-1.5">
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand/15 text-xs font-semibold text-accent" aria-hidden="true">{{ mb_strtoupper(mb_substr($account->profile->full_name, 0, 2)) }}</span>
                <div class="min-w-0 flex-1"><div class="truncate text-xs font-medium">{{ $account->profile->full_name }}</div><div class="mt-0.5 text-xs text-muted">{{ $account->isAdmin() ? 'Admin' : 'Mahasiswa' }}</div></div>
                <form method="post" action="{{ route('logout') }}">@csrf
                    <button type="submit" aria-label="Keluar" title="Keluar" class="grid size-11 place-items-center rounded-lg text-muted hover:bg-raised hover:text-ink"><x-icon name="log-out" class="size-4" /></button>
                </form>
            </div>
        @else
            <a href="{{ route('login') }}" class="button button-secondary w-full">{{ config('campusspace.demo') ? 'Masuk demo' : 'Masuk' }}</a>
        @endif
        @if(config('campusspace.demo'))<p class="px-2 text-xs text-muted sm:hidden">Mode demo</p>@endif
    </div>
</div>
