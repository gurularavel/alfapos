<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sifarişdə dəyişiklik: ekranlar bu siqnalı alıb öz siyahılarını yenidən yükləyir.
 * Payload qəsdən kiçikdir — məlumatın özü icazə yoxlaması ilə adi HTTP sorğusu ilə alınır.
 */
class OrderChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    /** @param string $type created | updated | kitchen | paid | cancelled */
    public function __construct(public int $tenantId, public int $orderId, public string $type, public string $source = 'pos', public ?int $tokenNo = null) {}

    public static function fromOrder(Order $order, string $type): self
    {
        return new self($order->tenant_id, $order->id, $type, $order->source, $order->token_no);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->tenantId)];
    }

    public function broadcastAs(): string
    {
        return 'order.changed';
    }

    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId, 'type' => $this->type, 'source' => $this->source, 'token_no' => $this->tokenNo];
    }
}
