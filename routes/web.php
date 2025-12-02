<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/push-test', function () {
    $deviceId = session()->get('test_device_id') ?? (string) Str::uuid();
    session(['test_device_id' => $deviceId]);
    return view('push_test', ['deviceId' => $deviceId]);
});
