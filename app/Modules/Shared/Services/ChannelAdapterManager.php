<?php

namespace App\Modules\Shared\Services;

use App\Modules\Shared\Contracts\ChannelAdapterInterface;
use InvalidArgumentException;

class ChannelAdapterManager
{
    /** @var array<string, class-string<ChannelAdapterInterface>> */
    private array $adapters = [];

    public function register(string $channel, string $adapterClass): void
    {
        $this->adapters[$channel] = $adapterClass;
    }

    public function adapter(string $channel): ChannelAdapterInterface
    {
        $normalized = match ($channel) {
            'voice', 'calls', 'sms' => 'phone',
            'fb', 'facebook' => 'messenger',
            'ig' => 'instagram',
            default => $channel,
        };

        $class = $this->adapters[$normalized] ?? $this->adapters[$channel] ?? null;
        if (! $class) {
            throw new InvalidArgumentException("No channel adapter registered for [{$channel}].");
        }

        return app($class);
    }

    public function has(string $channel): bool
    {
        $normalized = match ($channel) {
            'voice', 'calls', 'sms' => 'phone',
            'fb', 'facebook' => 'messenger',
            'ig' => 'instagram',
            default => $channel,
        };

        return isset($this->adapters[$normalized]) || isset($this->adapters[$channel]);
    }

    public function registered(): array
    {
        return array_keys($this->adapters);
    }
}
