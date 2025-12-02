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
        $notification = $this->notification;
        $targets = collect($notification->payload['targets'] ?? []);

        foreach ($targets as $targetTokenOrDeviceId) {

            $device = Device::where('device_id', $targetTokenOrDeviceId)
                        ->orWhere('token', $targetTokenOrDeviceId)
                        ->first();

            if (! $device) {
                Log::info("DeliverNotificationJob: device not found for target {$targetTokenOrDeviceId}");
                continue;
            }

            if ($device->online && $this->isConnected($device)) {
                event(new PushNotificationBroadcast($device->device_id, $notification->payload));

                // create delivery log with a proper array (no `[...]` placeholders)
                DeliveryLog::create([
                    'notification_id' => $notification->id,
                    'device_id' => $device->id,
                    'channel' => 'reverb',
                    'status' => 'sent',
                    'response' => 'broadcasted via reverb',
                ]);

                continue;
            }

            // Fallback: platform-specific (these are stubs — adapt to your impl)
            if (in_array($device->platform, ['android', 'web'])) {
                $res = $this->sendToFcm($device, $notification);

                DeliveryLog::create([
                    'notification_id' => $notification->id,
                    'device_id' => $device->id,
                    'channel' => 'fcm',
                    'status' => $res['ok'] ? 'sent' : 'failed',
                    'response' => json_encode($res),
                ]);
            } elseif ($device->platform === 'ios') {
                $res = $this->sendToApns($device, $notification);

                DeliveryLog::create([
                    'notification_id' => $notification->id,
                    'device_id' => $device->id,
                    'channel' => 'apns',
                    'status' => $res['ok'] ? 'sent' : 'failed',
                    'response' => json_encode($res),
                ]);
            } else {
                Log::warning("Unknown platform for device {$device->id}: {$device->platform}");
            }
        }

        // Optional: change notification status
        $notification->update(['status' => 'processing']);
    }

    protected function isConnected(Device $device): bool
    {
        return (bool) $device->online;
    }

    protected function sendToFcm(Device $device, Notification $notification): array
    {
        Log::info("sendToFcm stub -> token: {$device->token}");
        return ['ok' => true, 'message' => 'fcm-stub'];
    }

    protected function sendToApns(Device $device, Notification $notification): array
    {
        Log::info("sendToApns stub -> token: {$device->token}");
        return ['ok' => true, 'message' => 'apns-stub'];
    }
}
