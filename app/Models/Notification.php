<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
     protected $fillable = [
        'notification_id','sender_id','payload','priority','ttl','status'
    ];

    protected $casts = [
        'payload' => 'array',
        'ttl' => 'integer',
    ];
}
