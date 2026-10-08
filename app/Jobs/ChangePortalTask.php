<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Services\Channels\PortalDriver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ChangePortalTask implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<string, mixed> $data */
    public function __construct(public string $deliveryId, public string $action, public array $data) {}

    public function handle(PortalDriver $driver): void
    {
        $delivery = NotificationDelivery::findOrFail($this->deliveryId);
        $expected = $this->action === 'complete' ? 'completion_queued' : 'withdrawal_queued';
        if ($delivery->status !== $expected) {
            return;
        }

        $outcome = $driver->changeTask($delivery, $this->action, $this->data);
        $delivery->attempts()->create([
            'operation' => $this->action, 'status' => $outcome->status,
            'error' => $outcome->error, 'created_at' => now(),
        ]);
        if ($outcome->status === 'accepted') {
            $delivery->update(['status' => $this->action === 'complete' ? 'completed' : 'withdrawn', 'last_error' => null]);
        } elseif ($outcome->retryable && $this->attempts() < $this->tries) {
            $this->release($this->attempts() < 2 ? 60 : 300);
        } else {
            $delivery->update(['status' => 'accepted', 'last_error' => $outcome->error]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::whereKey($this->deliveryId)
            ->whereIn('status', ['completion_queued', 'withdrawal_queued'])
            ->update(['status' => 'accepted', 'last_error' => '待办状态同步最终失败']);
    }
}
