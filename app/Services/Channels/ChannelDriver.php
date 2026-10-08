<?php

namespace App\Services\Channels;

use App\Models\NotificationDelivery;
use App\Models\NotificationRequest;
use Illuminate\Support\Collection;

interface ChannelDriver
{
    /** @param Collection<int, NotificationDelivery> $deliveries
     * @return array<string, ChannelOutcome>
     */
    public function send(Collection $deliveries, NotificationRequest $notification): array;
}
