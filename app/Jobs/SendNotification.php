<?php

namespace App\Jobs;

use App\Models\NotificationRequest;
use App\Services\Channels\ChannelOutcome;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @param array<int, string>|null $deliveryIds */
    public function __construct(public string $notificationId, public ?string $channel = null, public ?array $deliveryIds = null) {}

    public function handle(): void
    {
        $notification = NotificationRequest::findOrFail($this->notificationId);
        $retry = false;

        foreach (config('notifications.drivers') as $channel => $driverClass) {
            if ($this->channel !== null && $channel !== $this->channel) {
                continue;
            }
            $driver = app($driverClass);
            $query = $notification->deliveries()->where('channel', $channel)->whereIn('status', ['queued', 'submitted']);
            if ($this->deliveryIds !== null) {
                $query->whereIn('id', $this->deliveryIds);
            }
            $deliveries = $query->get();
            if ($deliveries->isEmpty()) {
                continue;
            }

            $deliveries->each(fn ($delivery) => $delivery->update(['status' => 'submitted', 'attempts' => $delivery->attempts + 1]));
            $results = $driver->send($deliveries, $notification);

            foreach ($deliveries as $delivery) {
                $outcome = $results[$delivery->id] ?? ChannelOutcome::failed('渠道未返回结果', true);
                $shouldRetry = $outcome->retryable && $delivery->attempts < $this->tries;
                $delivery->update([
                    'status' => $shouldRetry ? 'queued' : $outcome->status,
                    'provider_ref' => $outcome->providerRef,
                    'last_error' => $outcome->error,
                ]);
                $delivery->attempts()->create([
                    'operation' => 'send', 'status' => $outcome->status,
                    'provider_ref' => $outcome->providerRef, 'error' => $outcome->error,
                    'created_at' => now(),
                ]);
                $retry = $retry || $shouldRetry;
            }
        }

        $this->refreshStatus($notification);
        if ($retry) {
            $this->release($this->attempts() < 2 ? 60 : 300);
        }
    }

    private function refreshStatus(NotificationRequest $notification): void
    {
        $statuses = $notification->deliveries()->pluck('status');
        $complete = $statuses->filter(fn ($status) => in_array($status, ['accepted', 'completed', 'withdrawn'], true))->count();
        $queued = $statuses->filter(fn ($status) => in_array($status, ['queued', 'submitted', 'completion_queued', 'withdrawal_queued'], true))->count();
        $notification->update(['status' => $queued > 0 ? 'processing' : ($complete === $statuses->count() ? 'accepted' : ($complete > 0 ? 'partial' : 'failed'))]);
    }

    public function failed(?Throwable $exception): void
    {
        $notification = NotificationRequest::find($this->notificationId);
        if (! $notification) {
            return;
        }
        $query = $notification->deliveries()->whereIn('status', ['queued', 'submitted']);
        if ($this->channel !== null) {
            $query->where('channel', $this->channel);
        }
        if ($this->deliveryIds !== null) {
            $query->whereIn('id', $this->deliveryIds);
        }
        $query->update([
            'status' => 'failed', 'last_error' => '队列任务最终失败',
        ]);
        $this->refreshStatus($notification);
    }
}
