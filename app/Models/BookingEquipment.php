<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingEquipment extends Model
{
    protected $table = 'booking_equipment';

    public $incrementing = false;

    public $timestamps = false;

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
