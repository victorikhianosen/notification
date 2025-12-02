<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


Broadcast::channel('devices.{deviceId}', function ($user, $deviceId) {
    return $user && $user->devices()->where('device_id',$deviceId)->exists();
});
