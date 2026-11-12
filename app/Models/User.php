<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $table = 'campus_private.users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'email', 'password', 'email_verified_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'immutable_datetime'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'id');
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(CampusNotification::class, 'notifiable')->latest();
    }

    public function isAdmin(): bool
    {
        return $this->profile?->role === 'admin';
    }

    public function hasVerifiedEmail(): bool
    {
        return ! config('campusspace.verify_email') || $this->email_verified_at !== null;
    }
}
