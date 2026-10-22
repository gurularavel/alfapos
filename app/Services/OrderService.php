<?php

namespace App\Services;

use App\Support\Realtime;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use App\Models\StoreTable;
use App\Models\Tenant;
use App\Support\PosTerminal;
use App\Support\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(protected Tenancy $tenancy) {}

    /**
     * Səbət elementlərini ([menu_item_id, variant_id, addon_ids[], quantity, notes]) bazadakı qiymətlərlə
     * order_items sətirlərinə çevirir. Qiymətlər heç vaxt klientdən götürülmür.
     */
    public function buildLines(array $cart): array
    {
        $items = MenuItem::with(['variants', 'addons', 'tax'])
            ->whereIn('id', collect($cart)->pluck('menu_item_id'))
            ->where('is_enabled', true)
            ->get()
            ->keyBy('id');

        // Vergi biznes səviyyəsində söndürülübsə sətirlər vergisiz yazılır (qiymət dəyişmir)
        $taxEnabled = $this->tenancy->tenant()?->is_tax_enabled ?? true;

        $lines = [];
        foreach ($cart as $row) {
            $item = $items->get($row['menu_item_id'] ?? null);
            if (! $item) {
                throw ValidationException::withMessages(['cart' => 'Menyu məhsulu tapılmadı və ya deaktivdir.']);
            }

            $variant = isset($row['variant_id']) && $row['variant_id']
                ? $item->variants->firstWhere('id', (int) $row['variant_id'])
                : null;
            $addons = $item->addons->whereIn('id', array_map('intval', $row['addon_ids'] ?? []))->values();

            $unit = (float) ($variant?->price ?? $item->price) + (float) $addons->sum('price');

            $lines[] = [
                'menu_item_id' => $item->id,
                'variant_id' => $variant?->id,
                'title' => $item->title,
                'variant_title' => $variant?->title,
                'addons' => $addons->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'price' => (float) $a->price])->all() ?: null,
                'price' => round($unit, 2),
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                'tax_rate' => $taxEnabled ? (float) ($item->tax?->rate ?? 0) : 0.0,
                'tax_type' => $item->tax?->type ?? 'exclusive',
                'notes' => isset($row['notes']) ? mb_substr($row['notes'], 0, 100) : null,
            ];
        }

        return $lines;
    }

    /**
     * Cəmləri hesablayır. Exclusive vergi üstə gəlir, inclusive vergi qiymətin içindədir.
     * Servis haqqı endirimdən sonrakı məbləğin faizidir; faiz Tenant::serviceRateFor() ilə müəyyən olunur.
     */
    public function totals(iterable $lines, ?string $discountType, float $discountValue, float $serviceRate): array
    {
        $subTotal = 0.0;
        $tax = 0.0;
        $exclusiveTax = 0.0;
        foreach ($lines as $line) {
            $amount = (float) data_get($line, 'price') * (int) data_get($line, 'quantity');
            $rate = (float) data_get($line, 'tax_rate');
            $subTotal += $amount;
            if (data_get($line, 'tax_type') === 'inclusive') {
                $tax += $amount - $amount / (1 + $rate / 100);
            } else {
                $exclusiveTax += $amount * $rate / 100;
            }
        }
        $tax += $exclusiveTax;

        $discount = match ($discountType) {
            'percentage' => $subTotal * min(100, max(0, $discountValue)) / 100,
            'fixed' => min($subTotal, max(0, $discountValue)),
            default => 0.0,
        };

        $service = ($subTotal - $discount) * $serviceRate / 100;

        return [
            'sub_total' => round($subTotal, 2),
            'discount_total' => round($discount, 2),
            'tax_total' => round($tax, 2),
            'service_charge_total' => round($service, 2),
            'total' => round($subTotal - $discount + $exclusiveTax + $service, 2),
        ];
    }

    /** Depozit yalnız zalda, eyni (depozitli) masanın sifarişləri birlikdə ödənəndə tətbiq olunur. */
    public function depositTable(Collection $orders): ?StoreTable
    {
        $tableIds = $orders->pluck('store_table_id')->unique();
        if ($tableIds->count() !== 1 || ! $tableIds->first() || $orders->contains(fn ($o) => $o->delivery_type !== 'dinein')) {
            return null;
        }
        $table = StoreTable::find($tableIds->first());

        return $table?->hasDeposit() ? $table : null;
    }

    /** Saxlanmış sifarişin cəmləri (servis haqqı qaydası ilə). */
    public function orderTotals(Order $order, iterable $lines, Tenant $tenant): array
    {
        return $this->totals($lines, $order->discount_type, (float) $order->discount_value,
            $tenant->serviceRateFor($order->delivery_type, $order->no_service_charge));
    }

    public function nextToken(): int
    {
        $last = Order::whereDate('created_at', today())->lockForUpdate()->max('token_no');

        return ($last ?? 0) + 1;
    }

    /**
     * POS və ya QR-dan sifariş yaradır. $data: delivery_type, customer_id, store_table_id, discount_type,
     * discount_value, notes, status (draft|created), draft_reference, source.
     */
    public function create(array $cart, array $data): Order
    {
        $lines = $this->buildLines($cart);
        if (! $lines) {
            throw ValidationException::withMessages(['cart' => 'Səbət boşdur.']);
        }

        return DB::transaction(function () use ($lines, $data) {
            $order = Order::create([
                'token_no' => $this->nextToken(),
                'delivery_type' => $data['delivery_type'] ?? 'dinein',
                'customer_id' => $data['customer_id'] ?? null,
                'store_table_id' => $data['store_table_id'] ?? null,
                'source' => $data['source'] ?? 'pos',
                'status' => $data['status'] ?? 'created',
                'draft_reference' => $data['draft_reference'] ?? null,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? 0,
                'no_service_charge' => (bool) ($data['no_service_charge'] ?? false),
                'notes' => $data['notes'] ?? null,
                'created_by' => app(PosTerminal::class)->effectiveId(),
            ]);
            $order->items()->createMany($lines);

            if ($order->status !== 'draft') {
                $this->deductStock($order);
            }

            if ($order->status !== 'draft') {
                Realtime::order($order, 'created');
            }

            return $order->load('items');
        });
    }

    /**
     * Bir və ya bir neçə sifarişi (məs. eyni masanın) ödəyib faktura yaradır.
     */
    public function pay(Collection $orders, int $paymentTypeId, ?int $customerId = null, bool $complete = true): Invoice
    {
        $tenant = $this->tenancy->tenant();

        return DB::transaction(function () use ($orders, $paymentTypeId, $customerId, $complete, $tenant) {
            $sum = ['sub_total' => 0, 'discount_total' => 0, 'tax_total' => 0, 'service_charge_total' => 0, 'total' => 0];
            foreach ($orders as $order) {
                $lines = $order->items->where('status', '!=', 'cancelled');
                foreach ($this->orderTotals($order, $lines, $tenant) as $k => $v) {
                    $sum[$k] += $v;
                }
            }

            // Kabinet/VIP depoziti: hesab masanın minimum məbləğindən azdırsa fərq əlavə olunur
            $deposit = (float) ($this->depositTable($orders)?->deposit ?? 0);
            $sum['deposit_amount'] = $deposit;
            $sum['deposit_topup'] = StoreTable::depositTopup($sum['total'], $deposit);
            $sum['total'] = round($sum['total'] + $sum['deposit_topup'], 2);

            $invoice = Invoice::create($sum + [
                'number' => $this->nextInvoiceNumber(),
                'customer_id' => $customerId ?? $orders->first()->customer_id,
                'payment_type_id' => $paymentTypeId,
                'status' => 'paid',
                'created_by' => app(PosTerminal::class)->effectiveId(),
            ]);

            foreach ($orders as $order) {
                $order->update([
                    'invoice_id' => $invoice->id,
                    'payment_status' => 'paid',
                    'status' => $complete ? 'completed' : $order->status,
                ]);
                if ($complete) {
                    $order->items()->whereNotIn('status', ['cancelled'])->update(['status' => 'completed']);
                }
            }

            AuditLog::record('invoice.created', $invoice, ['total' => $invoice->total]);
            $orders->each(fn ($o) => Realtime::order($o, 'paid'));

            return $invoice;
        });
    }

    public function cancel(Order $order, ?string $reason = null): void
    {
        DB::transaction(function () use ($order, $reason) {
            $wasDeducted = $order->status !== 'draft';
            $order->update(['status' => 'cancelled']);
            $order->items()->update(['status' => 'cancelled']);
            if ($wasDeducted) {
                $this->restoreStock($order);
            }
            AuditLog::record('order.cancelled', $order, array_filter(['reason' => $reason]));
            Realtime::order($order, 'cancelled');
        });
    }

    public function nextInvoiceNumber(): string
    {
        $count = Invoice::lockForUpdate()->count() + 1;

        return 'INV-'.str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }

    /** Resept əsasında stokdan silir (məhsul, variant və addon reseptləri). */
    public function deductStock(Order $order, int $sign = -1): void
    {
        $order->loadMissing('items');
        $recipes = RecipeItem::whereIn('menu_item_id', $order->items->pluck('menu_item_id')->filter())->get();
        if ($recipes->isEmpty()) {
            return;
        }

        $usage = [];
        foreach ($order->items as $line) {
            $addonIds = collect($line->addons)->pluck('id')->all();
            foreach ($recipes->where('menu_item_id', $line->menu_item_id) as $recipe) {
                $applies = match (true) {
                    (bool) $recipe->addon_id => in_array($recipe->addon_id, $addonIds),
                    (bool) $recipe->variant_id => $recipe->variant_id === $line->variant_id,
                    default => true,
                };
                if ($applies) {
                    $usage[$recipe->inventory_item_id] = ($usage[$recipe->inventory_item_id] ?? 0) + $recipe->quantity * $line->quantity;
                }
            }
        }

        foreach ($usage as $itemId => $qty) {
            InventoryItem::whereKey($itemId)->increment('quantity', $sign * $qty);
            StockMovement::create([
                'inventory_item_id' => $itemId,
                'type' => $sign < 0 ? 'sale' : 'in',
                'quantity' => $sign * $qty,
                'note' => ($sign < 0 ? 'Sifariş #' : 'Ləğv edilmiş sifariş #').$order->token_no,
                'order_id' => $order->id,
                'user_id' => app(PosTerminal::class)->effectiveId(),
            ]);
        }
    }

    public function restoreStock(Order $order): void
    {
        $this->deductStock($order, 1);
    }
}
