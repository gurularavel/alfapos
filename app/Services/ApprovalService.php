<?php

namespace App\Services;

use App\Models\User;
use App\Support\PosTerminal;
use App\Support\Tenancy;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Menecer təsdiqi: ləğv, qaytarma, sifariş/çek ləğvi və endirim yalnız "APPROVE" icazəli işçi (və ya admin)
 * tərəfindən edilə bilər. Əməliyyatı edən özü icazəlidirsə — o təsdiqçidir; deyilsə, menecer PIN-ini yazır.
 * Səhv PIN cəhdləri məhdudlaşdırılır ki, ofisiant menecerin PIN-ini təxmin edə bilməsin.
 */
class ApprovalService
{
    /** Frontend bu mesajla PIN pəncərəsini açır. */
    public const REQUIRED = 'Menecer təsdiqi lazımdır.';

    protected const MAX_ATTEMPTS = 5;

    protected const DECAY_SECONDS = 300;

    public function __construct(protected PosTerminal $terminal, protected Tenancy $tenancy) {}

    public function operator(): ?User
    {
        return $this->terminal->effectiveUser() ?? auth()->user();
    }

    /** Cari işçi özü təsdiq edə bilirmi (PIN tələb olunmur)? */
    public function canApproveSelf(): bool
    {
        return (bool) $this->operator()?->hasScope('APPROVE');
    }

    /**
     * @throws ValidationException approver_pin açarı ilə
     */
    public function approve(?string $pin): User
    {
        $operator = $this->operator();
        if ($operator?->hasScope('APPROVE')) {
            return $operator;
        }
        if ($pin === null || $pin === '') {
            throw ValidationException::withMessages(['approver_pin' => self::REQUIRED]);
        }

        $key = 'approve:'.$this->tenancy->id().':'.($operator?->id ?? request()->ip());
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['approver_pin' => 'Çox səhv cəhd. '.ceil(RateLimiter::availableIn($key) / 60).' dəqiqə sonra yenidən yoxlayın.']);
        }

        $approver = preg_match('/^\d{4}$/', $pin) ? User::findByPin($this->tenancy->id(), $pin) : null;
        if (! $approver || ! $approver->hasScope('APPROVE')) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages(['approver_pin' => $approver
                ? "{$approver->name} təsdiq icazəsinə malik deyil."
                : 'PIN yanlışdır.']);
        }
        RateLimiter::clear($key);

        return $approver;
    }
}
