<?php

namespace Tests\Feature;

use App\Rules\Turnstile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
        config(['services.turnstile' => ['site_key' => 'site-key', 'secret_key' => 'secret-key', 'hostnames' => ['alfapos.app']]]);
    }

    protected function cloudflare(array $result): void
    {
        Http::fake([Turnstile::VERIFY_URL => Http::response($result)]);
    }

    protected function login(array $extra = []): TestResponse
    {
        return $this->from(route('login'))->post(route('login'), ['email' => 'demo@pos.test', 'password' => 'password', ...$extra]);
    }

    public function test_forms_render_the_widget_with_their_action(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('data-sitekey="site-key"', false)->assertSee('data-action="login"', false)
            ->assertSee('challenges.cloudflare.com/turnstile/v0/api.js');
        $this->get(route('register'))->assertOk()->assertSee('data-action="register"', false);
    }

    public function test_login_requires_a_token_before_checking_the_password(): void
    {
        Http::fake();

        $this->login()->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_valid_token_logs_in_and_is_verified_with_secret_and_ip(): void
    {
        $this->cloudflare(['success' => true, 'action' => 'login', 'hostname' => 'alfapos.app']);

        $this->login(['cf-turnstile-response' => 'token-1'])->assertRedirect();
        $this->assertAuthenticated();
        Http::assertSent(fn ($request) => $request['secret'] === 'secret-key' && $request['response'] === 'token-1' && $request['remoteip'] === '127.0.0.1');
    }

    public function test_rejects_failed_token_wrong_action_and_foreign_hostname(): void
    {
        Http::fake([Turnstile::VERIFY_URL => Http::sequence()
            ->push(['success' => false, 'error-codes' => ['timeout-or-duplicate']])
            ->push(['success' => true, 'action' => 'register', 'hostname' => 'alfapos.app'])
            ->push(['success' => true, 'action' => 'login', 'hostname' => 'evil.example'])]);

        foreach (range(1, 3) as $attempt) {
            $this->login(['cf-turnstile-response' => 'token'])->assertSessionHasErrors('cf-turnstile-response');
            $this->assertGuest();
        }
        Http::assertSentCount(3);
    }

    public function test_fails_closed_when_cloudflare_is_unreachable(): void
    {
        Http::fake([Turnstile::VERIFY_URL => Http::sequence()
            ->pushFailedConnection()
            ->push('', 500)]);
        $this->login(['cf-turnstile-response' => 'token'])->assertSessionHasErrors('cf-turnstile-response');
        $this->login(['cf-turnstile-response' => 'token'])->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
    }

    public function test_register_is_gated_by_its_own_action(): void
    {
        $data = ['business_name' => 'Yeni Kafe', 'name' => 'Sahib', 'email' => 'yeni@kafe.az', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'terms' => '1', 'cf-turnstile-response' => 'token'];

        Http::fake([Turnstile::VERIFY_URL => Http::sequence()
            ->push(['success' => true, 'action' => 'login', 'hostname' => 'alfapos.app'])
            ->push(['success' => true, 'action' => 'register', 'hostname' => 'alfapos.app'])]);
        $this->post(route('register'), $data)->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseMissing('users', ['email' => 'yeni@kafe.az']);

        $this->post(route('register'), $data)->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'yeni@kafe.az']);
    }
}
