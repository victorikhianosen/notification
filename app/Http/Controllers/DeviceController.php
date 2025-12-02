<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    use HttpResponses;
    public function register(Request $req)
    {
        $validated = $req->validate([
            'device_id' => 'required|uuid',
            'platform' => 'required|string',
            'token' => 'nullable|string',
            'capabilities' => 'nullable|array',
        ]);

        $device = Device::updateOrCreate(
            ['device_id' => $validated['device_id']],
            array_merge($validated, ['user_id' => $req->user()->id ?? null, 'last_seen_at' => now(), 'reverb_channel' => "devices.{$validated['device_id']}"])
        );

        return $this->success('Device Registered successfully', [
            'device' => $device
        ]);
    }
}
