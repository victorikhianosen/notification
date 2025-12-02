<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
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
            array_merge($validated, ['user_id' => $req->user()->id ?? null, 'last_seen_at' => now()])
        );

        return response()->json(['ok' => true, 'device' => $device], 200);
    }
}
