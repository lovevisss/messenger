<?php

namespace App\Services\Channels;

use App\Models\NotificationRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class SmsDriver implements ChannelDriver
{
    public function send(Collection $deliveries, NotificationRequest $notification): array
    {
        $results = [];
        $settings = config('notifications.sms');

        if (collect(['ec_name', 'ap_id', 'secret_key', 'sign'])->contains(fn ($key) => blank($settings[$key]))) {
            foreach ($deliveries as $delivery) {
                $results[$delivery->id] = ChannelOutcome::failed('短信渠道尚未配置');
            }

            return $results;
        }

        foreach ($deliveries->chunk(5000) as $batch) {
            $mobiles = $batch->pluck('address')->implode(',');
            $content = $notification->content;
            $serial = $settings['add_serial'] ?? '';
            $mac = md5($settings['ec_name'].$settings['ap_id'].$settings['secret_key'].$mobiles.$content.$settings['sign'].$serial);
            $payload = [
                'ecName' => $settings['ec_name'], 'apId' => $settings['ap_id'],
                'mobiles' => $mobiles, 'content' => $content, 'sign' => $settings['sign'],
                'addSerial' => $serial, 'mac' => $mac,
            ];

            try {
                $response = Http::timeout(15)->withBody(base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 'application/json')->post($settings['url']);
                $body = $response->json();
                $code = is_array($body) ? ($body['rspcod'] ?? '') : '';
                $accepted = $response->successful() && $code === 'success' && ($body['success'] ?? true) !== false;
                $reference = $body['msgGroup'] ?? $body['mosGroup'] ?? null;
                $outcome = $accepted ? ChannelOutcome::accepted($reference) : ChannelOutcome::failed('MAS 拒绝：'.($code ?: 'HTTP '.$response->status()), $response->serverError());
            } catch (Throwable $exception) {
                $outcome = ChannelOutcome::failed('MAS 连接失败：'.class_basename($exception), true);
            }

            foreach ($batch as $delivery) {
                $results[$delivery->id] = $outcome;
            }
        }

        return $results;
    }
}
