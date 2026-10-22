<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\SitePage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
    }

    public function test_public_pages_render_with_company_contacts_and_footer(): void
    {
        foreach (['home', 'about', 'contact', 'faq', 'privacy', 'terms'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('055 703 80 08')->assertSee('012 310 10 08')->assertSee('support@kvadrat.az')
                ->assertSee('VÖEN 2701395461')->assertSee('Həsən bəy prospekti 78')
                ->assertSee(route('privacy'))->assertSee(route('terms'))->assertSee('AlfaPOS v'.config('app.version'));
        }

        $this->get(route('about'))->assertSee('<strong>Kvadrat</strong> şirkəti', false);
        $this->get(route('faq'))->assertSee('Masanın hesabını necə bağlayıram?')->assertSee('Şəhər: 012 310 10 08');
        $this->get(route('privacy'))->assertSee('Fərdi məlumatlar haqqında')->assertSee('id="b1"', false)->assertDontSee('{email}');
        $this->get(route('contact'))->assertSee('tel:+994557038008', false)->assertSee('tel:+994123101008', false)
            ->assertSee('wa.me/994557038008', false)->assertSee('40.3938117', false)->assertSee('maps.app.goo.gl/zq4EyWu9hUAHm3EMA', false);
        $this->get(route('register'))->assertSee('İstifadə şərtləri');
    }

    public function test_superadmin_edits_contacts_pages_and_faq(): void
    {
        $this->actingAs(User::where('email', 'super@pos.test')->first());
        foreach (['contacts', 'pages', 'faq'] as $tab) {
            $this->get(route('admin.site.index', ['tab' => $tab]))->assertOk();
        }

        $this->put(route('admin.site.settings'), [
            'company_name' => 'Kvadrat MMC', 'phone' => '050 111 22 33', 'city_phone' => '012 555 55 55', 'email' => 'info@kvadrat.az',
            'whatsapp' => '', 'voen' => '1234567890', 'address' => 'Yeni ünvan 1', 'map_url' => '', 'map_lat' => '', 'map_lng' => '', 'work_hours' => '',
        ])->assertSessionHasNoErrors();

        $this->get(route('admin.site.pages.edit', 'privacy'))->assertOk();
        $this->postJson(route('admin.site.preview'), ['body' => "## Başlıq\n\n<script>alert(1)</script> {email}"])
            ->assertOk()->assertJsonPath('html', fn ($h) => str_contains($h, '<h2>Başlıq</h2>') && ! str_contains($h, '<script>') && str_contains($h, 'info@kvadrat.az'));
        $this->put(route('admin.site.pages.update', 'privacy'), ['title' => 'Məxfilik', 'description' => '', 'body' => "## Yeni bölmə\n\nƏlaqə: {telefon}"])->assertSessionHasNoErrors();

        $this->post(route('admin.site.faqs.store'), ['group' => 'Yeni', 'question' => 'Test sualı?', 'answer' => 'Bəli, {şirkət}.', 'is_active' => 1])->assertSessionHasNoErrors();
        $hidden = Faq::where('question', 'Hansı printerlər dəstəklənir?')->first();
        $this->put(route('admin.site.faqs.update', $hidden), ['group' => $hidden->group, 'question' => $hidden->question, 'answer' => $hidden->answer])->assertSessionHasNoErrors();

        auth()->logout();
        $this->get(route('contact'))->assertSee('050 111 22 33')->assertSee('012 555 55 55')->assertSee('VÖEN 1234567890')->assertDontSee('maps.google.com');
        $this->get(route('privacy'))->assertSee('Yeni bölmə')->assertSee('050 111 22 33');
        $this->get(route('faq'))->assertSee('Test sualı?')->assertSee('Bəli, Kvadrat MMC.')->assertDontSee('Hansı printerlər dəstəklənir?');
    }

    public function test_site_admin_is_superadmin_only(): void
    {
        $this->actingAs(User::where('email', 'demo@pos.test')->first());
        $this->get(route('admin.site.index'))->assertForbidden();
        $this->put(route('admin.site.pages.update', 'privacy'), ['title' => 'x', 'body' => 'x'])->assertForbidden();
        $this->assertSame('Məxfilik siyasəti', SitePage::bySlug('privacy')->title);
    }
}
