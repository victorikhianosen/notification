<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PushNotificationBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payload;
    public $deviceId;

    public function __construct(string $deviceId, array $payload)
    {
        $this->deviceId = $deviceId;
        $this->payload = $payload;
    }

    // Use double-quoted string so PHP interpolates {$this->deviceId}
    public function broadcastOn()
    {
        return new PrivateChannel("devices.{$this->deviceId}");
    }

    public function broadcastWith()
    {
        return $this->payload;
    }
}
