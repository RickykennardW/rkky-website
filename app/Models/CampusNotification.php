<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class CampusNotification extends DatabaseNotification
{
    protected $table = 'campus_private.notifications';
}
