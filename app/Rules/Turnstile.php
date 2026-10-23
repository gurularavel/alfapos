<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloudflare Turnstile tokenini serverdə yoxlayır (cf-turnstile-response sahəsi).
 * Uğurlu sayılır yalnız: success, gözlənilən action və icazəli hostname. Cloudflare-ə çatmaq olmursa — rədd (fail closed).
 * Token birdəfəlikdir: təkrar göndərilən token Cloudflare tərəfindən rədd edilir.
 */
class Turnstile implements ValidationRule
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(protected string $action) {}

    public static function enabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = 'Robot olmadığınızı təsdiqləyin və yenidən cəhd edin.';

        if (! is_string($value) || $value === '' || strlen($value) > 2048) {
            $fail($message);

            return;
        }

        try {
            $result = Http::asForm()->timeout(10)->post(self::VERIFY_URL, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ])->throw()->json();
        } catch (Throwable $e) {
            Log::warning('Turnstile yoxlaması alınmadı: '.$e->getMessage());
            $fail('Təhlükəsizlik yoxlaması hazırda alınmadı. Bir az sonra yenidən cəhd edin.');

            return;
        }

        $passed = ($result['success'] ?? false) === true
            && ($result['action'] ?? null) === $this->action
            && in_array($result['hostname'] ?? null, config('services.turnstile.hostnames'), true);

        if (! $passed) {
            $fail($message);
        }
    }
}
