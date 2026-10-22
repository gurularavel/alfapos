<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KitchenController extends Controller
{
    public function index()
    {
        return view('kitchen.index');
    }

    public function feed()
    {
        return Order::with(['items' => fn ($q) => $q->whereIn('status', ['created', 'preparing', 'ready']), 'table', 'customer:id,name'])
            ->whereIn('status', ['created', 'preparing'])
            ->whereHas('items', fn ($q) => $q->whereIn('status', ['created', 'preparing']))
            ->orderBy('created_at')->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'token_no' => $o->token_no,
                'title' => $o->table?->label() ?? ($o->customer?->name ?? Order::DELIVERY_TYPES[$o->delivery_type]),
                'delivery_type' => Order::DELIVERY_TYPES[$o->delivery_type],
                'minutes' => (int) $o->created_at->diffInMinutes(now()),
                'notes' => $o->notes,
                'items' => $o->items->map(fn (OrderItem $i) => [
                    'id' => $i->id, 'title' => $i->title, 'variant' => $i->variant_title, 'qty' => $i->quantity,
                    'addons' => collect($i->addons)->pluck('title')->implode(', '), 'notes' => $i->notes, 'status' => $i->status,
                ]),
            ]);
    }

    public function updateItem(Request $request, OrderItem $item)
    {
        // OrderItem tenant scope-suz modeldir — sifariş vasitəsilə yoxlayırıq
        $order = Order::findOrFail($item->order_id);
        $data = $request->validate(['status' => ['required', Rule::in(['preparing', 'ready'])]]);

        $item->update($data);
        $this->syncOrderStatus($order);

        return response()->json(['message' => 'Yeniləndi.']);
    }

    public function markAll(Order $order)
    {
        $order->items()->whereIn('status', ['created', 'preparing'])->update(['status' => 'ready']);
        $this->syncOrderStatus($order);

        return response()->json(['message' => 'Sifariş hazırdır.']);
    }

    protected function syncOrderStatus(Order $order): void
    {
        $statuses = $order->items()->where('status', '!=', 'cancelled')->pluck('status');
        if ($statuses->isEmpty() || in_array($order->status, ['completed', 'cancelled'])) {
            return;
        }

        $order->update(['status' => match (true) {
            $statuses->every(fn ($s) => in_array($s, ['ready', 'completed'])) => 'ready',
            $statuses->contains('preparing') || $statuses->contains('ready') => 'preparing',
            default => 'created',
        }]);
    }
}
