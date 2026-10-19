<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ChangePortalTask;
use App\Jobs\SendNotification;
use App\Models\NotificationDelivery;
use App\Models\NotificationRequest;
use App\Services\PersonnelDirectory;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PDOException;
use RuntimeException;

class NotificationController extends Controller
{
    public function store(Request $request, PersonnelDirectory $directory): JsonResponse
    {
        $client = $request->attributes->get('client_system');
        $data = $request->validate([
            'request_id' => ['required', 'string', 'max:120'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', Rule::in(array_keys(config('notifications.drivers'))), 'distinct'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
            'recipients' => ['required', 'array', 'min:1', 'max:10000'],
            'recipients.*.id' => ['required', 'string', 'max:120', 'distinct'],
            'recipients.*.email' => ['nullable', 'string', 'max:255'],
            'recipients.*.mobile' => ['nullable', 'string', 'max:255'],
            'recipients.*.portal_account' => ['nullable', 'string', 'max:120'],
            'recipients.*.business_task_id' => ['nullable', 'string', 'max:120'],
            'recipients.*.previous_business_task_id' => ['nullable', 'string', 'max:120'],
            'portal' => ['nullable', 'array'],
            'portal.node_id' => ['nullable', 'string', 'max:120'],
            'portal.node_name' => ['nullable', 'string', 'max:255'],
            'portal.trans_type_code' => ['nullable', 'string', 'max:120'],
            'portal.trans_type_name' => ['nullable', 'string', 'max:255'],
            'portal.initiator' => ['nullable', 'string', 'max:120'],
            'portal.handle_url' => ['nullable', 'url', 'max:2000'],
            'portal.view_url' => ['nullable', 'url', 'max:2000'],
            'portal.mobile_handle_url' => ['nullable', 'url', 'max:2000'],
            'portal.mobile_view_url' => ['nullable', 'url', 'max:2000'],
            'portal.summary' => ['nullable', 'array'],
            'portal.summary.*' => ['string', 'max:255'],
        ]);

        $existing = NotificationRequest::where('client_system_id', $client->id)->where('request_id', $data['request_id'])->first();
        if ($existing) {
            return response()->json($this->resource($existing), 200);
        }

        $channels = $data['channels'];
        $errors = [];
        foreach ($data['recipients'] as $index => $recipient) {
            if (in_array('portal', $channels, true) && blank($recipient['business_task_id'] ?? null)) {
                $errors["recipients.$index.business_task_id"] = '门户待办要求业务任务号';
            }
        }
        if (in_array('portal', $channels, true)) {
            foreach (['node_id', 'node_name', 'trans_type_code', 'trans_type_name'] as $field) {
                if (blank($data['portal'][$field] ?? null)) {
                    $errors["portal.$field"] = '门户待办要求此字段';
                }
            }
            $taskIds = array_column($data['recipients'], 'business_task_id');
            if (count($taskIds) !== count(array_unique($taskIds))) {
                $errors['recipients'] = '业务任务号在一次请求中必须唯一';
            }
            $used = NotificationDelivery::where('client_system_id', $client->id)->whereIn('business_task_id', $taskIds)->exists();
            if ($used) {
                $errors['recipients'] = '业务任务号已被其他请求使用';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        try {
            $people = $directory->lookup(array_column($data['recipients'], 'id'));
        } catch (QueryException|PDOException|RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => '本地人员快照暂时不可用，请稍后重试'], 503);
        }

        try {
            $notification = DB::transaction(function () use ($client, $data, $channels, $people) {
                $notification = NotificationRequest::create([
                    'client_system_id' => $client->id, 'request_id' => $data['request_id'],
                    'title' => $data['title'], 'content' => $data['content'],
                    'channels' => $channels, 'portal' => $data['portal'] ?? null,
                    'status' => 'queued',
                ]);
                foreach ($data['recipients'] as $recipient) {
                    foreach ($channels as $channel) {
                        [$address, $error] = $this->deliveryAddress($recipient, $channel, $people);
                        $notification->deliveries()->create([
                            'client_system_id' => $client->id, 'recipient_id' => $recipient['id'],
                            'channel' => $channel, 'address' => $address ?? '',
                            'status' => $error === null ? 'queued' : 'failed',
                            'last_error' => $error,
                            'business_task_id' => $channel === 'portal' ? $recipient['business_task_id'] : null,
                            'portal_task_id' => $channel === 'portal' && $error === null ? (string) Str::uuid() : null,
                            'metadata' => $channel === 'portal' ? ['previous_business_task_id' => $recipient['previous_business_task_id'] ?? null] : null,
                        ]);
                    }
                }

                $failed = $notification->deliveries()->where('status', 'failed')->count();
                $queued = $notification->deliveries()->where('status', 'queued')->count();
                if ($failed > 0) {
                    $notification->update(['status' => $queued > 0 ? 'processing' : 'failed']);
                }

                foreach ($channels as $channel) {
                    $size = match ($channel) {
                        'email' => 3,
                        'sms' => 5000,
                        'portal' => 1,
                        'portal_message' => 1,
                        default => 10,
                    };
                    $notification->deliveries()->where('channel', $channel)->where('status', 'queued')->pluck('id')
                        ->chunk($size)->each(fn ($ids) => SendNotification::dispatch($notification->id, $channel, $ids->all())->afterCommit());
                }

                return $notification;
            });
        } catch (QueryException $exception) {
            $existing = NotificationRequest::where('client_system_id', $client->id)
                ->where('request_id', $data['request_id'])->first();
            if ($existing) {
                return response()->json($this->resource($existing));
            }

            throw $exception;
        }

        return response()->json($this->resource($notification), 202);
    }

    /**
     * @param  array<string, mixed>  $recipient
     * @param  array<string, array{email: ?string, mobile: ?string}|null>  $people
     * @return array{?string, ?string}
     */
    private function deliveryAddress(array $recipient, string $channel, array $people): array
    {
        $id = $recipient['id'];
        if (! array_key_exists($id, $people)) {
            return [null, '学工号未在人员信息库中找到'];
        }
        if ($people[$id] === null) {
            return [null, '学工号对应多条人员记录'];
        }

        $field = match ($channel) {
            'email' => 'email',
            'sms' => 'mobile',
            default => 'portal_account',
        };
        $source = match ($channel) {
            'email' => $people[$id]['email'],
            'sms' => $people[$id]['mobile'],
            default => $id,
        };
        $override = $recipient[$field] ?? null;
        $address = trim((string) (filled($override) ? $override : $source));
        if ($address === '') {
            return [null, $channel === 'email' ? '人员信息库未填写电子邮箱' : '人员信息库未填写移动电话'];
        }
        if ($channel === 'email' && (strlen($address) > 255 || filter_var($address, FILTER_VALIDATE_EMAIL) === false)) {
            return [null, '电子邮箱格式无效'];
        }
        if ($channel === 'sms' && preg_match('/^1[3-9][0-9]{9}$/D', $address) !== 1) {
            return [null, '移动电话格式无效'];
        }

        return [$address, null];
    }

    public function show(Request $request, string $notification): JsonResponse
    {
        $client = $request->attributes->get('client_system');
        $record = NotificationRequest::where('client_system_id', $client->id)->findOrFail($notification);

        return response()->json($this->resource($record));
    }

    public function changePortal(Request $request, string $businessTaskId, string $action): JsonResponse
    {
        $client = $request->attributes->get('client_system');
        $delivery = NotificationDelivery::where('client_system_id', $client->id)
            ->where('channel', 'portal')->where('business_task_id', $businessTaskId)->firstOrFail();
        if (! in_array($action, ['complete', 'withdraw'], true)) {
            abort(404);
        }
        $data = $request->validate([
            'final' => ['nullable', 'boolean'],
            'opinion' => ['nullable', 'string', 'max:1000'],
            'creator' => ['nullable', 'string', 'max:120'],
        ]);
        if ($action === 'complete' && ! array_key_exists('final', $data)) {
            throw ValidationException::withMessages(['final' => '完成待办必须说明是否为最终节点']);
        }
        if ($action === 'withdraw' && blank($data['creator'] ?? null)) {
            throw ValidationException::withMessages(['creator' => '撤销待办必须提供创建人学工号']);
        }
        if (($action === 'complete' && in_array($delivery->status, ['withdrawal_queued', 'withdrawn'], true))
            || ($action === 'withdraw' && in_array($delivery->status, ['completion_queued', 'completed'], true))) {
            return response()->json(['message' => '待办已有冲突的状态操作'], 409);
        }
        if ($delivery->status === 'accepted') {
            $changed = NotificationDelivery::whereKey($delivery->id)->where('status', 'accepted')
                ->update(['status' => $action === 'complete' ? 'completion_queued' : 'withdrawal_queued']);
            if ($changed) {
                ChangePortalTask::dispatch($delivery->id, $action, $data);
            }
        } elseif (! in_array($delivery->status, ['completion_queued', 'withdrawal_queued', 'completed', 'withdrawn'], true)) {
            throw ValidationException::withMessages(['business_task_id' => '待办尚未成功创建']);
        }

        return response()->json(['business_task_id' => $businessTaskId, 'status' => $delivery->fresh()->status], 202);
    }

    /** @return array<string, mixed> */
    private function resource(NotificationRequest $notification): array
    {
        return [
            'id' => $notification->id, 'request_id' => $notification->request_id,
            'status' => $notification->status, 'created_at' => $notification->created_at,
            'deliveries' => $notification->deliveries()->get()->map(fn ($delivery) => [
                'recipient_id' => $delivery->recipient_id, 'channel' => $delivery->channel,
                'status' => $delivery->status, 'provider_ref' => $delivery->provider_ref,
                'business_task_id' => $delivery->business_task_id, 'attempts' => $delivery->attempts,
                'error' => $delivery->last_error,
            ]),
        ];
    }
}
