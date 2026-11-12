@if($booking->status === 'pending')
    <section class="panel grid gap-4 p-5">
        <h2 class="section-title">Review request</h2>
        <div class="grid items-start gap-4 lg:grid-cols-2">
            <form method="post" action="{{ route('admin.bookings.review', $booking) }}" class="grid gap-3" data-confirm="Approve this request and allocate its resources?">
                @csrf
                <input type="hidden" name="decision" value="approve">
                <p class="muted">Room and equipment availability is checked again at approval.</p>
                <button class="button button-primary justify-self-start"><x-icon name="calendar-check" class="size-4" /> Approve request</button>
            </form>
            <form method="post" action="{{ route('admin.bookings.review', $booking) }}" class="grid gap-3" data-confirm="Reject this request?">
                @csrf
                <input type="hidden" name="decision" value="reject">
                <label class="label">Rejection reason<textarea class="input min-h-20" name="reason" required minlength="3" maxlength="1000">{{ old('reason') }}</textarea></label>
                <button class="button button-danger justify-self-start">Reject request</button>
            </form>
        </div>
    </section>
@elseif($booking->status === 'approved')
    @if($booking->loans->whereNull('returned_at')->isNotEmpty())
        <form method="post" action="{{ route('admin.bookings.return', $booking) }}" class="panel grid gap-4 p-5" data-confirm="Record this equipment return?">
            @csrf
            <div><h2 class="section-title">Record equipment return</h2><p class="muted mt-1">Enter missing and damaged quantities for each borrowed item.</p></div>
            @foreach($booking->loans->whereNull('returned_at')->values() as $index => $loan)
                <fieldset class="grid gap-3 rounded-lg border border-line p-3 sm:grid-cols-2">
                    <legend class="px-1 text-sm font-medium">{{ $loan->equipment?->name }} · {{ $loan->quantity }} borrowed</legend>
                    <input type="hidden" name="items[{{ $index }}][equipment_id]" value="{{ $loan->equipment_id }}">
                    @foreach(['missing', 'damaged'] as $field)
                        <label class="label capitalize">{{ $field }}<input name="items[{{ $index }}][{{ $field }}]" class="input" type="number" required min="0" max="{{ $loan->quantity }}" value="{{ old('items.'.$index.'.'.$field, 0) }}"></label>
                    @endforeach
                </fieldset>
            @endforeach
            <label class="label">Condition notes<textarea class="input min-h-20" name="note" maxlength="1000">{{ old('note') }}</textarea></label>
            <button class="button button-primary justify-self-start"><x-icon name="package-check" class="size-4" /> Record return</button>
        </form>
    @elseif($booking->items->isNotEmpty() && $booking->loans->isEmpty())
        <section class="panel grid gap-3 p-5">
            <h2 class="section-title">Equipment pickup</h2>
            @if(now()->betweenIncluded($booking->starts_at, $booking->ends_at) && now() < $booking->ends_at)
                <form method="post" action="{{ route('admin.bookings.pickup', $booking) }}" data-confirm="Confirm that the requested equipment was collected?">@csrf
                    <button class="button button-primary"><x-icon name="package-check" class="size-4" /> Record pickup</button>
                </form>
            @else
                <p class="muted">Pickup can be recorded during the approved reservation window.</p>
            @endif
        </section>
    @elseif($booking->items->isEmpty() && $booking->ends_at <= now())
        <form method="post" action="{{ route('admin.bookings.complete', $booking) }}" data-confirm="Mark this reservation complete?">@csrf
            <button class="button button-primary"><x-icon name="circle-check" class="size-4" /> Complete reservation</button>
        </form>
    @endif
@endif
