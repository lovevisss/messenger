<?php

namespace App\Services\Channels;

use App\Models\NotificationRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class PortalMessageDriver implements ChannelDriver
{
    public function __construct(private PortalTokenProvider $tokens) {}

    public function send(Collection $deliveries, NotificationRequest $notification): array
    {
        $results = [];
        $settings = config('notifications.portal');

        foreach ($deliveries as $delivery) {
            if (blank($settings['base_url'] ?? null) || blank($settings['app_id'] ?? null)
                || blank($settings['message_type_code'] ?? null)
                || (blank($settings['poa_token'] ?? null)
                    && (blank($settings['client_id'] ?? null) || blank($settings['client_secret'] ?? null)))) {
                $results[$delivery->id] = ChannelOutcome::failed('门户消息渠道尚未配置');

                continue;
            }

            $payload = [
                'appId' => $settings['app_id'],
                'sendId' => $delivery->id,
                'messageTypeCode' => $settings['message_type_code'],
                'toPersons' => $delivery->address,
                'sendType' => ['WEBSITE'],
                'data' => [
                    'title' => $notification->title,
                    'paramValueJson' => ['content' => $notification->content],
                ],
            ];

            $results[$delivery->id] = $this->post($settings['base_url'], $payload, filled($settings['poa_token'] ?? null));
        }

        return $results;
    }

    private function post(string $baseUrl, array $payload, bool $manualToken): ChannelOutcome
    {
        $url = rtrim($baseUrl, '/').'/apis/messagecenter/v1/poaMessage/messageSend';

        try {
            $response = Http::timeout(20)->withToken($this->tokens->token())->post($url, $payload);
            if ($response->unauthorized() && ! $manualToken) {
                $response = Http::timeout(20)->withToken($this->tokens->token(true))->post($url, $payload);
            }

            $body = $response->json();
            if (! $response->successful() || ! is_array($body) || ($body['code'] ?? null) !== 0) {
                return ChannelOutcome::failed('门户消息接口失败：HTTP '.$response->status().'，code '.($body['code'] ?? 'unknown'),
                    $response->serverError() || $response->status() === 429);
            }
            if (! empty($body['data']['errorList'])) {
                return ChannelOutcome::failed('门户消息部分发送失败');
            }

            return ChannelOutcome::accepted((string) ($body['data']['sendId'] ?? $payload['sendId']));
        } catch (Throwable $exception) {
            return ChannelOutcome::failed('门户消息连接失败：'.class_basename($exception), true);
        }
    }
}
