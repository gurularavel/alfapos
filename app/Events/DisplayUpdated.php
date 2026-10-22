<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Müştəri ekranına səbətin vəziyyəti. Kanal public-dir (ekranda istifadəçi yoxdur),
 * amma adı 40 simvollu təsadüfi tokendir — yalnız linki bilən cihaz qoşula bilər.
 */
class DisplayUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public string $channelName, public array $state) {}

    public function broadcastOn(): array
    {
        return [new Channel($this->channelName)];
    }

    public function broadcastAs(): string
    {
        return 'display.updated';
    }

    public function broadcastWith(): array
    {
        return $this->state;
    }
}
