<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
  protected $fillable = [
        'device_id','user_id','platform','token','reverb_channel','capabilities','last_seen_at','online'
    ];


     protected $casts = [
        'capabilities' => 'array',
        'last_seen_at' => 'datetime',
        'online' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


}
