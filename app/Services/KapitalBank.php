<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Kapital Bank e-commerce API (sənəd: Kapital Bank E-commerce API Documentation).
 *  - POST /order                         → sifariş (order) yaradılır, müştəri hppUrl?id=&password= səhifəsində ödəyir
 *  - GET  /order/{id}?password=...       → statusu (FullyPaid, Declined, ...)
 * Autentifikasiya: BasicAuth (merchant login:parol). Bankdan qayıdış linkinə etibar edilmir — status həmişə API ilə yoxlanılır.
 */
class KapitalBank
{
    public function __construct(protected Tenant $tenant) {}

    public static function for(Tenant $tenant): self
    {
        return new self($tenant);
    }

    public function isTest(): bool
    {
        return $this->tenant->kapitalbank()['mode'] !== 'live';
    }

    protected function client(): PendingRequest
    {
        $settings = $this->tenant->kapitalbank();
        $login = $settings['login'];
        $password = $settings['password'];
        if ($this->isTest() && blank($login)) {
            [$login, $password] = [config('services.kapitalbank.test_login'), config('services.kapitalbank.test_password')];
        }

        return Http::baseUrl(config('services.kapitalbank.'.($this->isTest() ? 'test_url' : 'prod_url')))
            ->withBasicAuth((string) $login, (string) $password)
            ->acceptJson()->asJson()->timeout(20);
    }

    /**
     * Ödəniş sifarişi (Order_SMS — birmərhələli alış).
     *
     * @return array{id: string, password: string, url: string, hpp_url: string, status: ?string}
     */
    public function createOrder(float $amount, string $description, string $redirectUrl): array
    {
        $order = $this->request(fn () => $this->client()->post('order', ['order' => [
            'typeRid' => 'Order_SMS',
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'AZN',
            'language' => 'az',
            'description' => mb_substr($description, 0, 120),
            'hppRedirectUrl' => $redirectUrl,
        ]]))['order'] ?? [];

        if (empty($order['id']) || empty($order['password']) || empty($order['hppUrl'])) {
            throw new RuntimeException('Bank sifariş yaratmadı.');
        }

        return [
            'id' => (string) $order['id'],
            'password' => (string) $order['password'],
            'url' => self::paymentPageUrl($order['hppUrl'], (string) $order['id'], (string) $order['password']),
            'hpp_url' => $order['hppUrl'],
            'status' => $order['status'] ?? null,
        ];
    }

    public static function paymentPageUrl(string $hppUrl, string $id, string $password): string
    {
        return $hppUrl.'?'.http_build_query(['id' => $id, 'password' => $password]);
    }

    /** Sifarişin bankdakı statusu (məs. "Preparing", "FullyPaid", "Declined"). */
    public function orderStatus(string $id, string $password): string
    {
        $status = $this->request(fn () => $this->client()->get('order/'.rawurlencode($id), ['password' => $password]))['order']['status'] ?? null;

        if (! is_string($status) || $status === '') {
            throw new RuntimeException('Bankdan status alınmadı.');
        }

        return $status;
    }

    /** Ayarlarda "Bağlantını yoxla": giriş məlumatları ilə 0.01 ₼-lıq test sifarişi yaradılır (ödənilmir, vaxtı keçib bağlanır). */
    public function testConnection(): void
    {
        $this->createOrder(0.01, 'AlfaPOS bağlantı yoxlaması', route('home'));
    }

    protected function request(callable $send): array
    {
        try {
            $response = $send();
        } catch (Throwable $e) {
            throw new RuntimeException('Kapital Bank-a qoşulmaq alınmadı.', previous: $e);
        }

        if ($response->failed()) {
            $code = $response->json('errorCode');
            throw new RuntimeException(match ($code) {
                'InvalidLogin' => 'Kapital Bank: login və ya parol yanlışdır.',
                'InvalidAmt' => 'Kapital Bank: məbləğ yanlışdır.',
                default => 'Kapital Bank xətası: '.($response->json('errorDescription') ?: $code ?: 'HTTP '.$response->status()),
            });
        }

        return (array) $response->json();
    }
}
