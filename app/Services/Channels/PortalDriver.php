<?php

namespace App\Services\Channels;

use App\Models\NotificationDelivery;
use App\Models\NotificationRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class PortalDriver implements ChannelDriver
{
    public function __construct(private PortalTokenProvider $tokens) {}

    public function send(Collection $deliveries, NotificationRequest $notification): array
    {
        $results = [];

        foreach ($deliveries as $delivery) {
            $results[$delivery->id] = $this->createTask($delivery, $notification);
        }

        return $results;
    }

    public function createTask(NotificationDelivery $delivery, NotificationRequest $notification): ChannelOutcome
    {
        $portal = $notification->portal ?? [];
        $settings = config('notifications.portal');
        if (blank($settings['base_url']) || blank($settings['app_id']) || (blank($settings['poa_token']) && (blank($settings['client_id']) || blank($settings['client_secret'])))) {
            return ChannelOutcome::failed('门户渠道尚未配置');
        }

        $payload = [
            'appId' => $settings['app_id'],
            'nodeId' => $portal['node_id'], 'nodeName' => $portal['node_name'],
            'taskId' => $delivery->portal_task_id, 'taskName' => $notification->title,
            'transTypeCode' => $portal['trans_type_code'],
            'transTypeName' => $portal['trans_type_name'], 'autoCreateTransType' => true,
            'handlers' => $delivery->address,
            'initiator' => $portal['initiator'] ?? null,
            'handleUrl' => $portal['handle_url'] ?? null,
            'viewUrl' => $portal['view_url'] ?? null,
            'mHandleUrl' => $portal['mobile_handle_url'] ?? null,
            'mViewUrl' => $portal['mobile_view_url'] ?? null,
            'taskSummary' => $portal['summary'] ?? ['内容|'.mb_substr($notification->content, 0, 200)],
        ];

        $previous = $delivery->metadata['previous_business_task_id'] ?? null;
        if ($previous) {
            $previousDelivery = NotificationDelivery::where('client_system_id', $delivery->client_system_id)
                ->where('business_task_id', $previous)->where('channel', 'portal')->first();
            if (! $previousDelivery || ! in_array($previousDelivery->status, ['accepted', 'completed'], true)) {
                return ChannelOutcome::failed('上一条门户待办不存在或尚未创建');
            }
            $payload['previousTaskId'] = $previousDelivery->portal_task_id;
        }

        return $this->post('addTransaction', [$payload]);
    }

    /** @param array<string, mixed> $data */
    public function changeTask(NotificationDelivery $delivery, string $action, array $data): ChannelOutcome
    {
        $settings = config('notifications.portal');
        if (blank($settings['base_url']) || blank($settings['app_id']) || (blank($settings['poa_token']) && (blank($settings['client_id']) || blank($settings['client_secret'])))) {
            return ChannelOutcome::failed('门户渠道尚未配置');
        }

        if ($action === 'complete') {
            return $this->post('taskHandle', [[
                'appId' => $settings['app_id'], 'handler' => $delivery->address,
                'previousTaskId' => $delivery->portal_task_id,
                'status' => ($data['final'] ?? false) ? 'end' : 'none',
                'handleOpinions' => $data['opinion'] ?? '',
            ]]);
        }

        return $this->post('taskWithdraw', [[
            'appId' => $settings['app_id'], 'taskId' => $delivery->portal_task_id,
            'creator' => $data['creator'], 'status' => 'end',
        ]]);
    }

    /** @param array<int, array<string, mixed>> $payload */
    private function post(string $operation, array $payload): ChannelOutcome
    {
        $settings = config('notifications.portal');
        try {
            $response = Http::timeout(20)->withHeaders(['Authorization' => $this->tokens->token()])
                ->withBody(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'application/json')
                ->post(rtrim($settings['base_url'], '/').'/apis/ttc/v1/transaction/poa/'.$operation);
            if ($response->unauthorized() && blank($settings['poa_token'])) {
                $response = Http::timeout(20)->withHeaders(['Authorization' => $this->tokens->token(true)])
                    ->withBody(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'application/json')
                    ->post(rtrim($settings['base_url'], '/').'/apis/ttc/v1/transaction/poa/'.$operation);
            }
            $body = $response->json();
            if (! $response->successful() || ! is_array($body) || ($body['code'] ?? null) !== 0) {
                return ChannelOutcome::failed('门户接口失败：HTTP '.$response->status().'，code '.($body['code'] ?? 'unknown'),
                    $response->serverError() || $response->status() === 429);
            }
            if (! empty($body['data']['errorIds'])) {
                $error = $body['data']['errorIds'][0];
                if ($operation === 'addTransaction' && ($error['code'] ?? null) === 2003) {
                    return ChannelOutcome::accepted();
                }
                if (in_array($operation, ['taskHandle', 'taskWithdraw'], true) && ($error['code'] ?? null) === 2005) {
                    return ChannelOutcome::accepted();
                }

                return ChannelOutcome::failed('门户任务失败：'.($error['code'] ?? 'unknown'));
            }

            return ChannelOutcome::accepted();
        } catch (Throwable $exception) {
            return ChannelOutcome::failed('门户连接失败：'.class_basename($exception), true);
        }
    }
}
