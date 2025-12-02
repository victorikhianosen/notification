<?php

namespace App\Http\Controllers;

use App\Traits\HttpResponses;
use Illuminate\Support\Str;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Jobs\DeliverNotificationJob;
use App\Models\Device;

class PushController extends Controller
{
    use HttpResponses;

    public function send(Request $req)
    {
        $payload = $req->validate([
            'notification_id' => 'nullable|uuid',
            'targets' => 'required|array',
            'ttl' => 'nullable|integer',
            'priority' => 'nullable|string',
            'title' => 'nullable|string',
            'body' => 'nullable|string',
            'data' => 'nullable|array',
            'encrypt' => 'nullable|boolean'
        ]);

        $expanded = [];
        foreach ($payload['targets'] as $t) {
            if (is_string($t) && str_starts_with($t, 'user:')) {
                $userId = (int) substr($t, 5);
                $deviceIds = Device::where('user_id', $userId)->pluck('device_id')->toArray();
                $expanded = array_merge($expanded, $deviceIds);
            } else {
                $expanded[] = $t;
            }
        }
        $payload['targets'] = array_values(array_unique($expanded));

        $notification = Notification::create([
            'notification_id' => $payload['notification_id'] ?? Str::uuid(),
            'sender_id' => $req->user()->id ?? null,
            'payload' => $payload,
            'priority' => $payload['priority'] ?? 'normal',
            'ttl' => $payload['ttl'] ?? null,
            'status' => 'pending',
        ]);

        DeliverNotificationJob::dispatch($notification);

        return $this->success('Message pushed successfully', [
            'push_id' =>  $notification->notification_id
        ]);
    }
}
