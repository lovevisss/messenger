<?php

namespace App\Services\Channels;

class ChannelOutcome
{
    public function __construct(
        public string $status,
        public ?string $providerRef = null,
        public ?string $error = null,
        public bool $retryable = false,
    ) {}

    public static function accepted(?string $reference = null): self
    {
        return new self('accepted', $reference);
    }

    public static function failed(string $error, bool $retryable = false): self
    {
        return new self('failed', null, $error, $retryable);
    }
}
