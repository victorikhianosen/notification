<?php

namespace App\Http\Controllers;

use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use App\Models\Device;
use App\Models\Notification;
use App\Jobs\DeliverNotificationJob;

class PushController extends Controller
{
    use HttpResponses;

    /**
     * Accept Pushy-style payloads (and your older format).
     * Returns Pushy-like response.
     */
    public function send(Request $req)
    {
        // Accept raw body (Pushy style) - no strict validation here because Pushy fields vary.
        $body = $req->json()->all();

        // Normalize targets:
        // - allow "targets": ["a","b"]
        // - allow "to": "a" or "to": ["a","b"]
        $targets = [];
        if (!empty($body['targets']) && is_array($body['targets'])) {
            $targets = $body['targets'];
        } elseif (isset($body['to'])) {
            $targets = is_array($body['to']) ? $body['to'] : [ (string) $body['to'] ];
        }

        // Normalize notification fields (Pushy uses notification.title/body)
        $title = $body['title'] ?? $body['notification']['title'] ?? null;
        $text  = $body['body']  ?? $body['notification']['body']  ?? null;
        $badge = $body['notification']['badge'] ?? null;
        $sound = $body['notification']['sound'] ?? null;

        // Data payload
        $data = $body['data'] ?? [];

        // Other fields
        $ttl = $body['ttl'] ?? null;
        $priority = $body['priority'] ?? null;
        $encrypt = $body['encrypt'] ?? false;

        // Use incoming notification id if provided, otherwise generate one
        $incomingId = $body['notification_id'] ?? ($body['id'] ?? (string) Str::uuid());

        // Expand user:xxx to device_ids (optional behaviour, keep if you support user: targets)
        $expanded = [];
        foreach ($targets as $t) {
            if (is_string($t) && str_starts_with($t, 'user:')) {
                $userId = (int) substr($t, 5);
                $deviceIds = Device::where('user_id', $userId)->pluck('device_id')->toArray();
                $expanded = array_merge($expanded, $deviceIds);
            } else {
                $expanded[] = $t;
            }
        }
        $expanded = array_values(array_unique($expanded));

        // Build canonical payload (what your job expects)
        $payload = [
            'notification_id' => $incomingId,
            'targets' => $expanded,
            'ttl' => $ttl,
            'priority' => $priority,
            'title' => $title,
            'body' => $text,
            'badge' => $badge,
            'sound' => $sound,
            'data' => $data,
            'encrypt' => (bool) $encrypt,
        ];

        // Idempotency: return existing if same id already present
        $existing = Notification::where('notification_id', $incomingId)->first();
        if ($existing) {
            $deviceCount = count($existing->payload['targets'] ?? []);
            return response()->json([
                'success' => true,
                'id' => $existing->notification_id,
                'info' => ['devices' => $deviceCount],
            ]);
        }

        // Create record with race protection
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
            // Race: another request created same notification_id
            Log::warning("Notification create race for id {$incomingId}: " . $e->getMessage());
            $notification = Notification::where('notification_id', $incomingId)->first();
            if (!$notification) {
                throw $e; // rethrow unexpected error
            }
        }

        // Dispatch delivery job
        DeliverNotificationJob::dispatch($notification);

        // Count devices we will attempt to deliver to.
        // Note: If your targets are tokens that don't map to DB devices, job will skip them.
        $deviceCount = count($payload['targets']);

        // Pushy-style response
        return response()->json([
            'success' => true,
            'id' => $notification->notification_id,
            'info' => ['devices' => $deviceCount],
        ]);
    }
}
