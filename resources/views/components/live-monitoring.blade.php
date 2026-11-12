<section class="grid gap-4" x-data="roomMonitoring" data-url="{{ route('monitoring.snapshot') }}" aria-labelledby="room-monitoring-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="room-monitoring-title" class="section-title">Live Room Monitoring</h2>
            <p class="muted mt-1">Room status follows approved reservations. In Use means the scheduled reservation is in progress.</p>
        </div>
        @if(config('campusspace.demo') && request()->routeIs('monitoring.index'))
            <form method="post" action="{{ route('monitoring.sample') }}">
                @csrf
                <button class="button button-secondary"><x-icon name="clock-3" class="size-4" /> Load in-progress sample</button>
            </form>
        @endif
    </div>
    <p class="muted" role="status" x-show="!snapshot || error" x-text="error || 'Loading room status…'"></p>
    <template x-if="snapshot">
        <div class="grid gap-4">
            <dl class="grid grid-cols-2 gap-3 md:grid-cols-5">
                @foreach(['total' => 'Total rooms', 'available' => 'Available now', 'in_use' => 'In use', 'upcoming' => 'Upcoming reservations', 'unavailable' => 'Unavailable'] as $key => $label)
                    <div class="panel grid gap-1 p-3 sm:p-4">
                        <dt class="text-xs text-muted">{{ $label }}</dt>
                        <dd class="text-xl font-semibold tabular-nums" x-text="snapshot.summary.{{ $key }}"></dd>
                    </div>
                @endforeach
            </dl>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <template x-for="room in snapshot.rooms" :key="room.id">
                    <article class="panel grid content-start gap-3 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div><h3 class="font-semibold" x-text="room.name"></h3><p class="muted mt-1" x-text="room.location + ' · ' + room.capacity + ' people'"></p></div>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                  :class="badgeClass(room)" x-text="statusLabel(room)"></span>
                        </div>
                        <template x-if="room.current && room.status === 'in use'">
                            <div class="grid gap-1 rounded-lg border border-line bg-canvas p-3">
                                <p class="text-sm font-medium" x-text="room.current.title || 'Approved reservation'"></p>
                                <p class="muted" x-text="windowLabel(room.current)"></p>
                                <p class="flex items-center justify-between gap-2 text-sm">
                                    <span class="text-muted">Time remaining</span>
                                    <strong class="font-mono text-accent tabular-nums" x-text="remaining(room.current.end)"></strong>
                                </p>
                            </div>
                        </template>
                        <template x-if="room.next && room.status !== 'unavailable'">
                            <div class="grid gap-1 text-sm">
                                <p class="text-muted">Next reservation <span class="text-ink" x-show="room.next.title" x-text="room.next.title ? '· ' + room.next.title : ''"></span></p>
                                <p class="text-accent" x-text="windowLabel(room.next)"></p>
                            </div>
                        </template>
                        <p class="muted" x-show="room.status === 'available'">No upcoming approved reservation.</p>
                        <p class="muted" x-show="room.status === 'unavailable'">Room unavailable for booking.</p>
                    </article>
                </template>
            </div>
            <p class="muted" x-show="!snapshot.rooms.length">No rooms are in the catalog.</p>
        </div>
    </template>
    <noscript><p class="muted">Enable JavaScript to see live room status.</p></noscript>
</section>
