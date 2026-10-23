<?php

namespace Tests\Feature;

use App\Models\ChangelogEntry;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\PaymentType;
use App\Models\StockAlert;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangelogAndAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
    }

    protected function owner(): User
    {
        $admin = User::where('email', 'demo@pos.test')->first();
        app(Tenancy::class)->set($admin->tenant);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_home_page_shows_latest_releases_and_changelog_page_shows_all(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Yeniliklər · v'.config('app.version'))
            ->assertSee('Bildirişlər və yeniliklər')->assertSee('Qaytarma və fırıldağa nəzarət')->assertDontSee('İlk buraxılış')
            ->assertSee(route('changelog'));

        $this->get(route('changelog'))->assertOk()->assertSee('v1.0.0')->assertSee('İlk buraxılış')->assertSee('22 oktyabr 2026', false)
            ->assertSee('Düzəliş')->assertSee('Ofisiant mətbəxə getmiş sifarişi səbəbsiz ləğv edə bilirdi');
        $this->assertSame(config('app.version'), ChangelogEntry::published()->value('version'));
    }

    public function test_superadmin_manages_releases(): void
    {
        $this->actingAs(User::where('email', 'super@pos.test')->first());
        $this->get(route('admin.site.index', ['tab' => 'changelog']))->assertOk()->assertSee('v1.7.0');

        $this->post(route('admin.site.changelog.store'), ['version' => '1.9', 'released_at' => '2026-11-01', 'title' => 'X', 'new' => 'a'])
            ->assertSessionHasErrors(['version' => 'Versiya 1.8.0 formatında olmalıdır.']);
        $this->post(route('admin.site.changelog.store'), ['version' => '1.9.0', 'released_at' => '2026-11-01', 'title' => 'X', 'new' => "
 - 
"])
            ->assertSessionHasErrors(['new' => 'Ən azı bir dəyişiklik yazın.']);
        $this->post(route('admin.site.changelog.store'), ['version' => '1.8.0', 'released_at' => '2026-11-01', 'title' => 'X', 'new' => 'a'])
            ->assertSessionHasErrors('version');

        $this->post(route('admin.site.changelog.store'), [
            'version' => '1.9.0', 'released_at' => '2026-11-01', 'title' => 'Çatdırılma', 'is_published' => 1,
            'new' => "- Kuryer modulu\n\n• Wolt inteqrasiyası", 'fixed' => 'Qəbzdə tarix',
        ])->assertSessionHasNoErrors();
        $release = ChangelogEntry::where('version', '1.9.0')->first();
        $this->assertEquals([['type' => 'new', 'text' => 'Kuryer modulu'], ['type' => 'new', 'text' => 'Wolt inteqrasiyası'], ['type' => 'fixed', 'text' => 'Qəbzdə tarix']], $release->items);

        $this->put(route('admin.site.changelog.update', $release), ['version' => '1.9.0', 'released_at' => '2026-11-01', 'title' => 'Çatdırılma', 'new' => 'Kuryer modulu'])
            ->assertSessionHasNoErrors();
        auth()->logout();
        $this->get(route('changelog'))->assertDontSee('Kuryer modulu'); // gizli (is_published göndərilməyib)
    }

    public function test_release_management_is_superadmin_only(): void
    {
        $this->owner();
        $this->post(route('admin.site.changelog.store'), ['version' => '9.9.9', 'released_at' => '2026-11-01', 'title' => 'X', 'new' => 'a'])->assertForbidden();
        $this->assertNull(ChangelogEntry::where('version', '9.9.9')->first());
    }

    public function test_stock_alerts_follow_inventory_and_ready_stock(): void
    {
        $this->owner();
        // Demo: burger çörəyi minimumdan aşağıdır (8 → 7, min 10)
        $this->getJson(route('notifications.index'))->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('out', 0)
            ->assertJsonPath('items.0.title', 'Burger çörəyi')->assertJsonPath('items.0.level', 'low')->assertJsonPath('items.0.quantity', '7 ədəd');

        $milk = InventoryItem::where('title', 'Süd')->first();
        $this->post(route('inventory.movement', $milk), ['type' => 'out', 'quantity' => (float) $milk->quantity])->assertSessionHasNoErrors();
        $this->post(route('ready-stock.count', MenuItem::where('title', 'Dolma')->first()), ['actual' => 4])->assertSessionHasNoErrors();

        $this->getJson(route('notifications.index'))->assertJsonPath('unread', 3)->assertJsonPath('out', 1)
            ->assertJsonPath('items.0.title', 'Süd')->assertJsonPath('items.0.level', 'out')
            ->assertJson(fn ($json) => $json->where('items', fn ($items) => collect($items)->contains(fn ($i) => $i['title'] === 'Dolma' && $i['source'] === 'Hazır yemək' && $i['quantity'] === '4 porsiya'))->etc());

        $this->postJson(route('notifications.read'))->assertOk();
        $this->getJson(route('notifications.index'))->assertJsonPath('unread', 0);

        // Azalıb → bitib: yeni oxunmamış bildiriş; köhnəsi bağlanır
        $this->postJson(route('pos.store'), ['action' => 'pay', 'delivery_type' => 'takeaway', 'payment_type_id' => PaymentType::value('id'),
            'cart' => [['menu_item_id' => MenuItem::where('title', 'Dolma')->value('id'), 'quantity' => 4]]])->assertOk();
        $this->getJson(route('notifications.index'))->assertJsonPath('unread', 1);
        $this->assertSame(['low', 'out'], StockAlert::where('title', 'Dolma')->orderBy('id')->pluck('level')->all());

        // Stok bərpa olunanda bildiriş bağlanır
        $this->post(route('inventory.movement', $milk), ['type' => 'in', 'quantity' => 10000])->assertSessionHasNoErrors();
        $this->getJson(route('notifications.index'));
        $this->assertNotNull(StockAlert::where('title', 'Süd')->value('resolved_at'));
        $this->assertSame(0, StockAlert::open()->where('title', 'Süd')->count());
    }

    public function test_bell_is_shown_to_stock_staff_only(): void
    {
        $this->owner();
        $this->get(route('dashboard'))->assertOk()->assertSee('aria-label="Bildirişlər"', false)->assertSee('window.STOCK_ALERT_URLS', false);

        $this->actingAs(User::where('email', 'metbex@pos.test')->first());
        $this->getJson(route('notifications.index'))->assertOk();

        $this->actingAs(User::where('email', 'kassir@pos.test')->first());
        $this->get(route('pos'))->assertOk()->assertDontSee('aria-label="Bildirişlər"', false);
        $this->getJson(route('notifications.index'))->assertForbidden();
    }
}
