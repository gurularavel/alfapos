<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function edit()
    {
        return view('settings.store', ['store' => $this->tenancy->tenant()]);
    }

    public function update(Request $request)
    {
        $tenant = $this->tenancy->tenant();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'currency' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'timezone'],
            'daily_sales_goal' => ['required', 'numeric', 'min:0'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);
        foreach (['is_qr_menu_enabled', 'is_qr_order_enabled', 'is_feedback_enabled'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        if ($request->hasFile('logo')) {
            if ($tenant->logo) {
                Storage::disk('public')->delete($tenant->logo);
            }
            $data['logo'] = $request->file('logo')->store("tenants/{$tenant->id}", 'public');
        } else {
            unset($data['logo']);
        }

        $tenant->update($data);

        return back()->with('success', 'Mağaza məlumatları yeniləndi.');
    }

    public function editPrint()
    {
        return view('settings.print', ['store' => $this->tenancy->tenant()]);
    }

    /**
     * Çap ayarlarının önizləməsi: nümunə qəbz və ya mətbəx çeki (bazaya heç nə yazılmır).
     * Formadakı yadda saxlanmamış dəyərlər query ilə gəlir; ?print=1 olduqda çap pəncərəsi açılır.
     */
    public function printPreview(Request $request)
    {
        $tenant = $this->tenancy->tenant();
        $flags = ['show_store_details', 'show_customer_details', 'show_notes', 'print_token', 'kot_show_prices', 'auto_print'];
        if ($request->has('paper_width')) {
            $settings = [
                'paper_width' => in_array((int) $request->query('paper_width'), [58, 80]) ? (int) $request->query('paper_width') : 80,
                'header' => mb_substr((string) $request->query('header'), 0, 255),
                'footer' => mb_substr((string) $request->query('footer'), 0, 255),
            ];
            foreach ($flags as $flag) {
                $settings[$flag] = $request->boolean($flag);
            }
            $tenant->print_settings = $settings; // yalnız bu sorğu üçün, save() yoxdur
        }

        [$invoice, $order] = $this->sampleDocuments($tenant);
        $preview = ! $request->boolean('print');

        return $request->query('type') === 'kot'
            ? view('print.token', ['order' => $order, 'tenant' => $tenant, 'preview' => $preview])
            : view('print.receipt', ['invoice' => $invoice, 'tenant' => $tenant, 'preview' => $preview]);
    }

    /** Yaddaşda qurulan nümunə sənədlər (heç biri bazaya yazılmır). */
    protected function sampleDocuments(\App\Models\Tenant $tenant): array
    {
        $user = auth()->user();
        $table = new \App\Models\StoreTable(['title' => 'Masa 5', 'floor' => 'Zal']);
        $customer = new \App\Models\Customer(['name' => 'Elvin Məmmədov', 'phone' => '+994 55 111 22 33']);
        $items = collect([
            ['Plov', null, null, 14.00, 2, null],
            ['Latte', 'Orta', [['id' => 0, 'title' => 'Əlavə şot', 'price' => 1.5]], 7.50, 1, 'Şəkərsiz'],
            ['Ayran', null, null, 2.00, 2, null],
        ])->map(fn ($r) => new \App\Models\OrderItem([
            'title' => $r[0], 'variant_title' => $r[1], 'addons' => $r[2], 'price' => $r[3], 'quantity' => $r[4],
            'notes' => $r[5], 'tax_rate' => 18, 'tax_type' => 'inclusive', 'status' => 'created',
        ]));

        $order = new \App\Models\Order(['token_no' => 27, 'delivery_type' => 'dinein', 'notes' => 'Tez olsun, zəhmət olmasa', 'is_addition' => false]);
        $order->created_at = now();
        $order->setRelation('items', $items)->setRelation('table', $table)->setRelation('creator', $user)->setRelation('customer', $customer);

        $sub = $items->sum(fn ($i) => $i->lineTotal());
        $tax = round($sub - $sub / 1.18, 2);
        $service = $tenant->is_service_charge_enabled ? round($sub * (float) $tenant->service_charge / 100, 2) : 0;
        $invoice = new \App\Models\Invoice([
            'number' => 'NÜMUNƏ-0001', 'status' => 'paid', 'sub_total' => $sub, 'discount_total' => 0,
            'tax_total' => $tenant->is_tax_enabled ? $tax : 0, 'service_charge_total' => $service,
            'deposit_amount' => 0, 'deposit_topup' => 0, 'total' => $sub + $service,
        ]);
        $invoice->created_at = now();
        $invoice->setRelation('orders', collect([$order]))->setRelation('customer', $customer)
            ->setRelation('paymentType', new \App\Models\PaymentType(['title' => 'Nağd']))->setRelation('creator', $user);

        return [$invoice, $order];
    }

    public function updatePrint(Request $request)
    {
        $data = $request->validate([
            'paper_width' => ['required', 'in:58,80'],
            'header' => ['nullable', 'string', 'max:255'],
            'footer' => ['nullable', 'string', 'max:255'],
        ]);
        foreach (['show_store_details', 'show_customer_details', 'show_notes', 'print_token', 'kot_show_prices', 'auto_print'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $this->tenancy->tenant()->update(['print_settings' => $data]);

        return back()->with('success', 'Çap ayarları yeniləndi.');
    }
}
