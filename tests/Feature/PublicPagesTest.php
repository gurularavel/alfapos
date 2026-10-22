<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render_with_company_contacts_and_footer(): void
    {
        $this->seed(DatabaseSeeder::class);
        auth()->logout();

        foreach (['home', 'about', 'contact', 'faq', 'privacy', 'terms'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('055 703 80 08')->assertSee('support@kvadrat.az')
                ->assertSee(route('privacy'))->assertSee(route('terms'))->assertSee(route('faq'))
                ->assertSee('AlfaPOS v'.config('app.version'));
        }

        $this->get(route('about'))->assertSee('Kvadrat</b> şirkəti', false);
        $this->get(route('faq'))->assertSee('Masanın hesabını necə bağlayıram?');
        $this->get(route('privacy'))->assertSee('Fərdi məlumatlar haqqında');
        $this->get(route('contact'))->assertSee('tel:+994557038008', false)->assertSee('wa.me/994557038008', false);
        $this->get(route('register'))->assertSee('İstifadə şərtləri')->assertSee('Məxfilik siyasəti');
        $this->get(route('login'))->assertSee(route('privacy'));
    }
}
