<?php

namespace App\Http\Controllers;

use App\Traits\HttpResponses;
use Illuminate\Support\Str;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Jobs\DeliverNotificationJob;

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

    $notification = Notification::create([
        'notification_id' => $payload['notification_id'] ?? Str::uuid(),
        'sender_id' => $req->user()->id ?? null,
        'payload' => $payload,
        'priority' => $payload['priority'] ?? 'normal',
    ]);

    DeliverNotificationJob::dispatch($notification);

    return $this->success('Message pushed successfully', [
        'push_id' =>  $notification->notification_id
    ]);
}


}
