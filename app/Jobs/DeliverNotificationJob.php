<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\DeliveryLog;
use App\Models\Notification;
use App\Events\PushNotificationBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeliverNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Notification $notification;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    public function handle(): void
    {
        Log::info("DeliverNotificationJob starting for notification_id={$this->notification->notification_id}");

        $notification = $this->notification;
        $targets = collect($notification->payload['targets'] ?? []);

        foreach ($targets as $target) {
            Log::info("DeliverNotificationJob: processing target={$target}");

            $device = Device::where('device_id', $target)->orWhere('token', $target)->first();

            if (! $device) {
                Log::info("Device not found for target {$target}");
                continue;
            }

            // Realtime via Reverb/Echo
            if ($device->online && $this->isConnected($device)) {
                event(new PushNotificationBroadcast($device->device_id, $notification->payload));

                DeliveryLog::create([
                    'notification_id' => $notification->id,
                    'device_id' => $device->id,
                    'channel' => 'reverb',
                    'status' => 'sent',
                    'response' => 'broadcasted via reverb',
                ]);

                Log::info("Broadcasted to device {$device->device_id} via reverb");
                continue;
            }

            // Fallback to platform push
            if (in_array($device->platform, ['android', 'web'])) {
                $res = $this->sendToFcm($device, $notification);
                DeliveryLog::create([
                    'notification_id' => $notification->id,
                    'device_id' => $device->id,
                    'channel' => 'fcm',
                    'status' => $res['ok'] ? 'sent' : 'failed',
                    'response' => json_encode($res),
                ]);
                Log::info("FCM result for {$device->device_id}: " . json_encode($res));
            } elseif ($device->platform === 'ios') {
                $res = $this->sendToApns($device, $notification);
                DeliveryLog::create([
                    'notification_id' => $notification->id,
                    'device_id' => $device->id,
                    'channel' => 'apns',
                    'status' => $res['ok'] ? 'sent' : 'failed',
                    'response' => json_encode($res),
                ]);
                Log::info("APNs result for {$device->device_id}: " . json_encode($res));
            } else {
                Log::warning("Unknown platform {$device->platform} for device {$device->id}");
            }
        }

        // mark processing/done as required
        $notification->update(['status' => 'processing']);
        Log::info("DeliverNotificationJob finished for notification_id={$this->notification->notification_id}");
    }

    protected function isConnected(Device $device): bool
    {
        // local test: accept web clients even if online flag not set
        return $device->platform === 'web' ? true : (bool) $device->online;
    }


    protected function sendToFcm(Device $device, Notification $notification): array
    {
        if (empty($device->token)) return ['ok' => false, 'error' => 'no_token'];
        $key = env('FCM_SERVER_KEY'); // set in .env
        $payload = [
            'to' => $device->token,
            'priority' => 'high',
            'notification' => [
                'title' => $notification->payload['title'] ?? null,
                'body' => $notification->payload['body'] ?? null
            ],
            'data' => $notification->payload['data'] ?? []
        ];
        try {
            $resp = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'key=' . $key,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);
            return ['ok' => $resp->successful(), 'status' => $resp->status(), 'body' => $resp->body()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }


    protected function sendToApns(Device $device, Notification $notification): array
    {
        // === STUB ===
        // Replace with APNs HTTP/2 implementation (token-based)
        Log::info("sendToApns stub -> token: {$device->token}");
        return ['ok' => true, 'message' => 'apns-stub'];
    }
}
