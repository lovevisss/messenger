<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $notification_request_id
 * @property int $client_system_id
 * @property string $recipient_id
 * @property string $channel
 * @property string $address
 * @property array<string, mixed>|null $metadata
 * @property string $status
 * @property string|null $provider_ref
 * @property string|null $business_task_id
 * @property string|null $portal_task_id
 * @property int $attempts
 * @property string|null $last_error
 */
class NotificationDelivery extends Model
{
    use HasUuids;

    protected $fillable = ['notification_request_id', 'client_system_id', 'recipient_id', 'channel', 'address', 'metadata', 'status', 'provider_ref', 'business_task_id', 'portal_task_id', 'attempts', 'last_error'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return BelongsTo<NotificationRequest, $this> */
    public function notificationRequest(): BelongsTo
    {
        return $this->belongsTo(NotificationRequest::class);
    }

    /** @return HasMany<DeliveryAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class);
    }
}
