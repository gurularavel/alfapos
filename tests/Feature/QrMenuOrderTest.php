<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\StoreTable;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QrMenuOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_order_after_midnight_uses_the_restaurant_timezone(): void
    {
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
        $admin = User::where('email', 'demo@pos.test')->first();
        $tenant = $admin->tenant;
        $this->assertSame('Asia/Baku', $tenant->timezone);
        $table = StoreTable::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('title', 'Masa 1')->first();
        $item = MenuItem::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('title', 'Plov')->first();

        // Bakıda 01:30 = UTC-də hələ dünən (21:30)
        $this->travelTo(Carbon::parse('2026-10-24 01:30', 'Asia/Baku'));
        date_default_timezone_set('UTC');
        config(['app.timezone' => 'UTC']);
        app(Tenancy::class)->set(null);
        $lastToday = (int) Order::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('created_at', '>=', '2026-10-24 00:00:00')->max('token_no');

        $token = $this->postJson(route('qr.order', $tenant->qr_code), ['t' => $table->qr_token, 'cart' => [['menu_item_id' => $item->id, 'quantity' => 1]]])
            ->assertOk()->json('token_no');

        $order = Order::withoutGlobalScopes()->where('source', 'qr')->latest('id')->first();
        $this->assertSame('2026-10-24 01:30', $order->getRawOriginal('created_at') ? substr($order->getRawOriginal('created_at'), 0, 16) : null);
        $this->assertSame($lastToday + 1, $token); // Bakı vaxtı ilə bu günün token ardıcıllığı davam edir

        // Kassir POS-da QR sifarişi görür
        $this->actingAs($admin);
        $this->getJson(route('pos.qr'))->assertOk()->assertJsonPath('0.token_no', $token);
    }
}
