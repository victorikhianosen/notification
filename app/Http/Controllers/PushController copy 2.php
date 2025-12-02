<?php

namespace App\Http\Controllers;

use App\Traits\HttpResponses;
use Illuminate\Support\Str;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Jobs\DeliverNotificationJob;
use App\Models\Device;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

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

        $incomingId = $payload['notification_id'] ?? (string) Str::uuid();

        $existing = Notification::where('notification_id', $incomingId)->first();
        if ($existing) {
            return $this->success('Message already pushed', [
                'push_id' => $existing->notification_id,
                'status' => $existing->status,
            ]);
        }

        try {
            $notification = Notification::create([
                'notification_id' => $incomingId,
                'sender_id' => $req->user()->id ?? null,
                'payload' => $payload,
                'priority' => $payload['priority'] ?? 'normal',
                'ttl' => $payload['ttl'] ?? null,
                'status' => 'pending',
            ]);
        } catch (QueryException $e) {
            // Race condition: another process created the same id simultaneously
            Log::warning("Notification create race: " . $e->getMessage());
            $notification = Notification::where('notification_id', $incomingId)->first();
            if ($notification) {
                return $this->success('Message already pushed (race)', [
                    'push_id' => $notification->notification_id,
                    'status' => $notification->status,
                ]);
            }
            // If still no record, rethrow so caller sees error
            throw $e;
        }

        DeliverNotificationJob::dispatch($notification);

        return $this->success('Message pushed successfully', [
            'push_id' =>  $notification->notification_id
        ]);
    }
}
