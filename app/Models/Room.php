<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    public const FACILITIES = ['Projector', 'Air conditioning', 'Whiteboard', 'Audio system', 'Wi-Fi', 'Accessible entry'];

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['layout' => 'array', 'active' => 'boolean'];
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(RoomFacility::class)->orderBy('facility');
    }
}
