@php($admin = auth()->user()?->isAdmin())
<section class="grid min-w-0 gap-4" x-data="roomMonitoring" data-url="{{ route('monitoring.snapshot') }}" aria-label="Status ruangan">
    @if(config('campusspace.demo') && request()->routeIs('monitoring.index'))
        <form method="post" action="{{ route('monitoring.sample') }}" class="justify-self-start">
            @csrf
            <button class="button button-secondary"><x-icon name="clock-3" class="size-4" /> Tampilkan contoh penggunaan</button>
        </form>
    @endif
    <p class="muted" role="status" x-show="!snapshot || error" x-text="error || 'Memuat status ruangan…'"></p>
    <template x-if="snapshot">
        <div class="grid min-w-0 gap-4">
            <dl class="panel grid grid-cols-3 divide-x divide-line">
                @foreach(['available' => 'Tersedia sekarang', 'in_use' => 'Sedang digunakan', 'unavailable' => 'Tidak tersedia'] as $key => $label)
                    <div class="grid gap-1 p-3 sm:p-4">
                        <dd class="text-xl font-semibold tabular-nums" x-text="snapshot.summary.{{ $key }}"></dd>
                        <dt class="text-xs text-muted">{{ $label }}</dt>
                    </div>
                @endforeach
            </dl>
            <div class="grid gap-3 md:grid-cols-2">
                <template x-for="room in snapshot.rooms" :key="room.id">
                    <article class="panel grid content-start gap-3 p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div><h3 class="font-semibold" x-text="room.name"></h3><p class="muted mt-1" x-text="room.location + ' · ' + room.capacity + ' orang'"></p></div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="badgeClass(room)" x-text="statusLabel(room)"></span>
                        </div>
                        @if($admin)
                            <template x-if="room.current && room.status === 'in use'">
                                <div class="grid gap-2 rounded-lg border border-line bg-canvas p-3">
                                    <a class="w-fit text-sm font-medium text-accent hover:underline" :href="room.current.url" x-text="room.current.title"></a>
                                    <p class="muted" x-text="windowLabel(room.current)"></p>
                                    <p class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                        <span class="text-muted">Sisa waktu</span>
                                        <strong class="font-mono text-accent tabular-nums" x-text="remaining(room.current.end)"></strong>
                                    </p>
                                </div>
                            </template>
                            <template x-if="room.next && room.status !== 'unavailable'">
                                <p class="muted">Selanjutnya <a class="text-accent hover:underline" :href="room.next.url" x-text="room.next.title"></a> · <span x-text="windowLabel(room.next)"></span></p>
                            </template>
                        @else
                            <p class="muted" x-show="room.status === 'in use' && !crossedBoundary(room)"
                               x-text="room.next?.start === room.current?.end ? 'Ada peminjaman berikutnya setelah jadwal ini.' : (room.current ? 'Tersedia setelah ' + timeLabel(room.current.end) : '')"></p>
                            <p class="muted" x-show="room.status === 'upcoming' && !crossedBoundary(room)" x-text="room.next ? 'Tersedia hingga ' + timeLabel(room.next.start) : ''"></p>
                        @endif
                    </article>
                </template>
            </div>
            <p class="muted" x-show="!snapshot.rooms.length">Belum ada ruangan di katalog.</p>
        </div>
    </template>
    <noscript><p class="muted">Aktifkan JavaScript untuk melihat status terbaru.</p></noscript>
</section>
