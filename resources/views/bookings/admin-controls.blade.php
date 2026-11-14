@if($booking->status === 'pending')
    <section class="panel grid gap-4 p-5">
        <h2 class="section-title">Keputusan admin</h2>
        <div class="grid items-start gap-4 lg:grid-cols-2">
            <form method="post" action="{{ route('admin.bookings.review', $booking) }}" class="grid gap-3"
                  @unless(config('campusspace.demo')) data-confirm="Setujui pengajuan ini?" @endunless>
                @csrf
                <input type="hidden" name="decision" value="approve">
                <p class="muted">Jadwal dan stok diperiksa kembali sebelum disetujui.</p>
                <button class="button button-primary justify-self-start"><x-icon name="calendar-check" class="size-4" /> Setujui pengajuan</button>
            </form>
            <form method="post" action="{{ route('admin.bookings.review', $booking) }}" class="grid gap-3"
                  @unless(config('campusspace.demo')) data-confirm="Tolak pengajuan ini?" @endunless>
                @csrf
                <input type="hidden" name="decision" value="reject">
                <label class="label">Alasan penolakan<textarea class="input min-h-20" name="reason" required minlength="3" maxlength="1000">{{ old('reason') }}</textarea></label>
                <button class="button button-danger justify-self-start">Tolak pengajuan</button>
            </form>
        </div>
    </section>
@elseif($booking->status === 'approved' && !config('campusspace.demo'))
    @if($booking->loans->whereNull('returned_at')->isNotEmpty())
        <form method="post" action="{{ route('admin.bookings.return', $booking) }}" class="panel grid gap-4 p-5" data-confirm="Catat pengembalian ini?">
            @csrf
            <div><h2 class="section-title">Catat pengembalian peralatan</h2><p class="muted mt-1">Isi jumlah barang yang hilang atau rusak.</p></div>
            @foreach($booking->loans->whereNull('returned_at')->values() as $index => $loan)
                <fieldset class="grid gap-3 rounded-lg border border-line p-3 sm:grid-cols-2">
                    <legend class="px-1 text-sm font-medium">{{ $loan->equipment?->name }} · {{ $loan->quantity }} dipinjam</legend>
                    <input type="hidden" name="items[{{ $index }}][equipment_id]" value="{{ $loan->equipment_id }}">
                    @foreach(['missing', 'damaged'] as $field)
                        <label class="label capitalize">{{ $field }}<input name="items[{{ $index }}][{{ $field }}]" class="input" type="number" required min="0" max="{{ $loan->quantity }}" value="{{ old('items.'.$index.'.'.$field, 0) }}"></label>
                    @endforeach
                </fieldset>
            @endforeach
            <label class="label">Catatan kondisi<textarea class="input min-h-20" name="note" maxlength="1000">{{ old('note') }}</textarea></label>
            <button class="button button-primary justify-self-start"><x-icon name="package-check" class="size-4" /> Catat pengembalian</button>
        </form>
    @elseif($booking->items->isNotEmpty() && $booking->loans->isEmpty())
        <section class="panel grid gap-3 p-5">
            <h2 class="section-title">Pengambilan peralatan</h2>
            @if(now()->betweenIncluded($booking->starts_at, $booking->ends_at) && now() < $booking->ends_at)
                <form method="post" action="{{ route('admin.bookings.pickup', $booking) }}" data-confirm="Konfirmasi bahwa peralatan sudah diambil?">@csrf
                    <button class="button button-primary"><x-icon name="package-check" class="size-4" /> Catat pengambilan</button>
                </form>
            @else
                <p class="muted">Pengambilan bisa dicatat selama jadwal peminjaman.</p>
            @endif
        </section>
    @elseif($booking->items->isEmpty() && $booking->ends_at <= now())
        <form method="post" action="{{ route('admin.bookings.complete', $booking) }}" data-confirm="Tandai peminjaman ini selesai?">@csrf
            <button class="button button-primary"><x-icon name="circle-check" class="size-4" /> Selesaikan peminjaman</button>
        </form>
    @endif
@endif
