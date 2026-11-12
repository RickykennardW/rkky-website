<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled', 'completed'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'planner' => 'array',
            'required_facilities' => 'array',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingEquipment::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EquipmentLoan::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function getPeriodLabelAttribute(): string
    {
        $start = $this->starts_at->timezone(config('campusspace.timezone'));
        $end = $this->ends_at->timezone(config('campusspace.timezone'));

        return $start->format('d M Y · H:i').' – '.$end->format($start->isSameDay($end) ? 'H:i' : 'd M Y · H:i').' WIB';
    }
}
