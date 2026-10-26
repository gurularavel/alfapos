<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\PosTerminal;
use App\Support\Realtime;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KitchenController extends Controller
{
    public function index(Tenancy $tenancy)
    {
        return view('kitchen.index', [
            'soundNewOrder' => (bool) $tenancy->tenant()->terminalSetting('sound_new_order'),
            // Şöbə ekranı: cihaz öz şöbəsini seçir (bar, mətbəx); "çap stansiyası" olan şöbənin çekini bu cihaz çap edir
            'departments' => Department::orderBy('sort')->orderBy('id')->get(['id', 'name', 'print_at', 'is_default']),
        ]);
    }

    public function feed()
    {
        $defaultDepartment = Department::defaultId();

        return Order::with(['items' => fn ($q) => $q->whereIn('status', ['created', 'preparing', 'ready']), 'table', 'customer:id,name', 'creator:id,name'])
            ->tap(fn ($q) => app(PosTerminal::class)->scopeToBranch($q))
            ->whereIn('status', ['created', 'preparing'])
            ->whereHas('items', fn ($q) => $q->whereIn('status', ['created', 'preparing']))
            ->orderBy('created_at')->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'token_no' => $o->token_no,
                'title' => $o->table?->label() ?? ($o->customer?->name ?? Order::DELIVERY_TYPES[$o->delivery_type]),
                'delivery_type' => Order::DELIVERY_TYPES[$o->delivery_type],
                'waiter' => $o->creator?->name,
                'is_addition' => $o->is_addition,
                'transferred_from' => $o->transferred_from,
                'minutes' => (int) $o->created_at->diffInMinutes(now()),
                'notes' => $o->notes,
                'items' => $o->items->map(fn (OrderItem $i) => [
                    'id' => $i->id, 'department_id' => $i->department_id ?? $defaultDepartment, 'title' => $i->title, 'variant' => $i->variant_title, 'qty' => $i->quantity,
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
        Realtime::order($order, 'kitchen');

        return response()->json(['message' => 'Yeniləndi.']);
    }

    /** "Hamısı hazırdır": şöbə ekranından — yalnız həmin şöbənin məhsulları. */
    public function markAll(Request $request, Order $order)
    {
        $department = $request->integer('department_id') ?: null;
        $isDefault = $department && $department === Department::defaultId();
        $order->items()->whereIn('status', ['created', 'preparing'])
            ->when($department, fn ($q) => $q->where(fn ($w) => $w->where('department_id', $department)->when($isDefault, fn ($d) => $d->orWhereNull('department_id'))))
            ->update(['status' => 'ready']);
        $this->syncOrderStatus($order);
        Realtime::order($order, 'kitchen');

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
