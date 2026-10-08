<?php

namespace App\Services\Channels;

use App\Models\NotificationRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailDriver implements ChannelDriver
{
    public function send(Collection $deliveries, NotificationRequest $notification): array
    {
        $results = [];

        foreach ($deliveries as $delivery) {
            try {
                Mail::raw($notification->content, function ($message) use ($notification, $delivery) {
                    $message->to($delivery->address)->subject($notification->title);
                });
                $results[$delivery->id] = ChannelOutcome::accepted();
            } catch (Throwable $exception) {
                $results[$delivery->id] = ChannelOutcome::failed('邮件服务器连接或发送失败：'.class_basename($exception), true);
            }
        }

        return $results;
    }
}
