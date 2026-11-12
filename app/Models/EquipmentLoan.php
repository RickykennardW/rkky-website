<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentLoan extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['picked_up_at' => 'immutable_datetime', 'returned_at' => 'immutable_datetime', 'allocation_warning' => 'boolean'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
