<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $client_system_id
 * @property string $request_id
 * @property string $title
 * @property string $content
 * @property array<int, string> $channels
 * @property array<string, mixed>|null $portal
 * @property string $status
 */
class NotificationRequest extends Model
{
    use HasUuids;

    protected $fillable = ['client_system_id', 'request_id', 'title', 'content', 'channels', 'portal', 'status'];

    protected function casts(): array
    {
        return ['channels' => 'array', 'portal' => 'array'];
    }

    /** @return BelongsTo<ClientSystem, $this> */
    public function clientSystem(): BelongsTo
    {
        return $this->belongsTo(ClientSystem::class);
    }

    /** @return HasMany<NotificationDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
