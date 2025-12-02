<?php

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PushController;
use App\Http\Controllers\DeviceController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::post('devices/register', [DeviceController::class, 'register']);


Route::post('push', [PushController::class, 'send']); // protect in prod
Route::post('/devices/mark-online', function (Request $req) {
    $req->validate(['device_id' => 'required|string']);
    $device = Device::firstOrCreate(['device_id' => $req->device_id], [
        'platform' => $req->platform ?? 'web',
        'token' => $req->token ?? null,
        'reverb_channel' => "devices.{$req->device_id}"
    ]);
    $device->update([
        'online' => 1,
        'last_seen_at' => now(),
    ]);
    return response()->json(['ok' => true, 'device' => $device]);
});
