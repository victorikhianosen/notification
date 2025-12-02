<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

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

    public function broadcastOn()
    {
            return new Channel("devices.{$this->deviceId}");

        // return new PrivateChannel("devices.{$this->deviceId}");
    }

    public function broadcastWith()
    {
        return $this->payload;
    }
}
