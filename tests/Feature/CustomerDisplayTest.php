<?php

namespace Tests\Feature;

use App\Events\DisplayUpdated;
use App\Models\CustomerDisplay;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CustomerDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
        $this->admin = User::where('email', 'demo@pos.test')->first();
        app(Tenancy::class)->set($this->admin->tenant);
    }

    protected function cart(): array
    {
        return [
            'mode' => 'cart',
            'items' => [['title' => 'Latte', 'subtitle' => 'Böyük', 'qty' => 2, 'total' => 15]],
            'totals' => ['sub' => 15, 'discount' => 0, 'tax' => 2.29, 'service' => 0.75, 'total' => 15.75],
            'customer' => 'Elvin',
        ];
    }

    public function test_owner_creates_display_and_guest_device_opens_it(): void
    {
        $this->actingAs($this->admin)->get(route('settings.displays.index'))->assertOk();
        $this->post(route('settings.displays.store'), ['name' => 'Kassa 1'])->assertSessionHasNoErrors();
        $display = CustomerDisplay::firstOrFail();

        auth()->logout();
        $this->get(route('display.show', $display->token))->assertOk()->assertSee('Demo Restoran');
        $this->getJson(route('display.state', $display->token))->assertOk()->assertJson(['mode' => 'idle']);
        $this->assertNotNull($display->fresh()->last_seen_at);
        $this->get(route('display.show', 'yanlis-token'))->assertNotFound();
    }

    public function test_pos_push_updates_state_and_broadcasts_on_display_channel(): void
    {
        Event::fake([DisplayUpdated::class]);
        $display = CustomerDisplay::create(['name' => 'Kassa 1']);

        $this->actingAs($this->admin)->postJson(route('pos.display.push', $display), $this->cart())->assertNoContent();

        $this->getJson(route('display.state', $display->token))->assertOk()
            ->assertJsonPath('mode', 'cart')->assertJsonPath('items.0.title', 'Latte')
            ->assertJsonPath('totals.total', 15.75)->assertJsonPath('currency', '₼');
        Event::assertDispatched(DisplayUpdated::class, fn ($e) => $e->broadcastOn()[0]->name === 'display.'.$display->token);
    }

    public function test_other_tenant_cannot_push_to_display(): void
    {
        $display = CustomerDisplay::create(['name' => 'Kassa 1']);
        $other = app(TenantProvisioner::class)->create('Başqa', 'Owner', 'x@pos.test', 'password');
        app(Tenancy::class)->set($other->tenant);

        $this->actingAs($other)->postJson(route('pos.display.push', $display->id), $this->cart())->assertNotFound();
    }

    public function test_locked_terminal_can_still_reset_display(): void
    {
        $display = CustomerDisplay::create(['name' => 'Kassa 1']);
        $this->actingAs($this->admin)->postJson(route('pos.terminal.enter'))->assertOk();
        $this->postJson(route('pos.display.push', $display), ['mode' => 'idle'])->assertNoContent();
    }

    public function test_regenerated_link_invalidates_old_one(): void
    {
        $display = CustomerDisplay::create(['name' => 'Kassa 1']);
        $old = $display->token;
        $this->actingAs($this->admin)->put(route('settings.displays.update', $display), ['name' => 'Kassa A', 'regenerate' => 1])->assertSessionHasNoErrors();

        auth()->logout();
        $this->get(route('display.show', $old))->assertNotFound();
        $this->get(route('display.show', $display->fresh()->token))->assertOk();
    }

    public function test_display_closes_when_subscription_expires(): void
    {
        $display = CustomerDisplay::create(['name' => 'Kassa 1']);
        $this->admin->tenant->update(['status' => 'trial', 'trial_ends_at' => now()->subDay()]);
        $this->get(route('display.show', $display->token))->assertNotFound();
    }
}
