<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickPrintTest extends TestCase
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
        $this->actingAs($this->admin);
    }

    public function test_launcher_opens_pos_in_kiosk_printing_mode(): void
    {
        $response = $this->get(route('settings.print.launcher'))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="AlfaPOS-Kassa.bat"');
        $bat = $response->getContent();

        $this->assertStringContainsString('set "URL='.route('pos').'"', $bat);
        $this->assertStringContainsString('--kiosk-printing --user-data-dir="%PROFILE%"', $bat);
        $this->assertStringContainsString("goto run\r\n", $bat); // Windows sətir sonu
        $this->assertMatchesRegularExpression('/^[\x00-\x7F]*$/', $bat); // cmd üçün yalnız ASCII
        $this->get(route('settings.print'))->assertOk()->assertSee('Dialoqsuz (sürətli) çap')->assertSee(route('settings.print.launcher'));
    }

    public function test_launcher_needs_settings_permission(): void
    {
        $this->actingAs(User::where('email', 'kassir@pos.test')->first());
        $this->get(route('settings.print.launcher'))->assertForbidden();
    }

    public function test_receipt_prints_automatically_after_payment_unless_turned_off(): void
    {
        $this->get(route('pos'))->assertViewHas('posData', fn ($d) => $d['autoPrintReceipt'] === true);
        $this->get(route('orders.index'))->assertViewHas('autoPrintReceipt', true);
        $this->get(route('settings.print'))->assertSee('name="auto_print" value="1" checked', false);

        $this->put(route('settings.print.update'), ['paper_width' => 80, 'print_token' => 1])->assertSessionHasNoErrors();

        $this->get(route('pos'))->assertViewHas('posData', fn ($d) => $d['autoPrintReceipt'] === false && $d['autoPrintKot'] === true);
    }

    public function test_print_documents_print_themselves_on_load(): void
    {
        $invoice = \App\Models\Invoice::first();
        $this->get(route('invoices.receipt', $invoice))->assertSee('window.print()', false);
        $this->get(route('invoices.receipt', [$invoice, 'noprint' => 1]))->assertDontSee('setTimeout(() => window.print()', false);
    }
}
