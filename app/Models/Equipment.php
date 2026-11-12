<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    public const CATEGORIES = ['Audio', 'Presentation', 'Furniture', 'Power', 'Accessories'];

    public const STATUSES = ['available', 'maintenance', 'inactive'];

    protected $table = 'equipment';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    public function usableQuantity(): int
    {
        return $this->status === 'available' ? $this->total_quantity - $this->unusable_quantity : 0;
    }
}
