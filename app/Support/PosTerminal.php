<?php

namespace App\Support;

use App\Models\Terminal;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;

/**
 * POS terminal rejimi: cihaz bir hesabla daxil olur, ekran isə işçinin PIN-i ilə açılır.
 * Açıq olan işçi ("operator") sifarişlərin/çeklərin müəllifi olur və icazələr ona görə yoxlanılır.
 * Vəziyyət sessiyada saxlanılır: pos_terminal (bool), pos_operator ['id' => int, 'at' => timestamp].
 */
class PosTerminal
{
    protected ?User $operator = null;

    protected bool $resolved = false;

    /** Nəticə hansı sorğu üçün hesablanıb (eyni proses bir neçə sorğu emal edə bilər — testlər, Octane). */
    protected ?object $for = null;

    /** Bu cihazın (brauzerin) bağlı olduğu terminal — şifrələnmiş cookie, 5 il. */
    public const DEVICE_COOKIE = 'alfapos_terminal';

    protected ?Terminal $device = null;

    protected ?object $deviceFor = null;

    public function __construct(protected Tenancy $tenancy) {}

    protected function session(): Session
    {
        return session()->driver();
    }

    /** Cihazın terminalı (bu biznesə aid və aktivdirsə). Sifariş/çeklər bu terminala və onun filialına yazılır. */
    public function device(): ?Terminal
    {
        if ($this->deviceFor === request()) {
            return $this->device;
        }
        $this->deviceFor = request();

        $id = (int) request()->cookie(self::DEVICE_COOKIE);
        $terminal = $id && $this->tenancy->id()
            ? Terminal::with('branch')->where('is_active', true)->find($id)
            : null;
        if ($terminal && (! $terminal->last_seen_at || $terminal->last_seen_at->lt(now()->subMinute()))) {
            $terminal->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $this->device = $terminal;
    }

    /**
     * Cihaz terminala bağlıdırsa sorğunu onun filialı ilə məhdudlaşdırır (filialı yazılmamış köhnə qeydlər də görünür).
     * Bağlı deyilsə (panel, admin kompüteri) bütün filiallar görünür.
     */
    public function scopeToBranch($query, string $column = 'branch_id')
    {
        $branchId = $this->device()?->branch_id;

        return $query->when($branchId, fn ($q) => $q->where(fn ($w) => $w->where($column, $branchId)->orWhereNull($column)));
    }

    public function bindDevice(?Terminal $terminal): void
    {
        Cookie::queue($terminal
            ? Cookie::make(self::DEVICE_COOKIE, (string) $terminal->id, 60 * 24 * 365 * 5)
            : Cookie::forget(self::DEVICE_COOKIE));
        $this->device = $terminal?->load('branch');
        $this->deviceFor = request();
    }

    public function active(): bool
    {
        return (bool) $this->session()->get('pos_terminal');
    }

    public function enter(): void
    {
        $this->session()->put('pos_terminal', true);
        $this->lock();
    }

    public function exit(): void
    {
        $this->session()->forget(['pos_terminal', 'pos_operator']);
        $this->operator = null;
        $this->resolved = true;
        $this->for = request();
    }

    public function unlock(User $user): void
    {
        $this->session()->put('pos_operator', ['id' => $user->id, 'at' => now()->timestamp]);
        $this->operator = $user;
        $this->resolved = true;
        $this->for = request();
    }

    public function lock(): void
    {
        $this->session()->forget('pos_operator');
        $this->operator = null;
        $this->resolved = true;
        $this->for = request();
    }

    /** Terminalda ekranı açmış işçi; boşdursa və ya vaxtı keçibsə null. */
    public function operator(): ?User
    {
        if ($this->resolved && $this->for === request()) {
            return $this->operator;
        }
        $this->resolved = true;
        $this->for = request();
        $this->operator = null;

        $data = $this->session()->get('pos_operator');
        $tenant = $this->tenancy->tenant();
        if (! $this->active() || ! $data || ! $tenant) {
            return null;
        }

        // Klient taymeri işləməsə belə (tab bağlanıb və s.) server tərəfdə də kilidləyirik
        $timeout = max(10, (int) $tenant->terminalSetting('lock_timeout'));
        if (now()->timestamp - $data['at'] > $timeout + 30) {
            $this->lock();

            return null;
        }

        $user = User::where('tenant_id', $tenant->id)->where('is_active', true)->find($data['id']);
        if ($user) {
            $this->session()->put('pos_operator.at', now()->timestamp);
        }

        return $this->operator = $user;
    }

    public function locked(): bool
    {
        return $this->active() && $this->operator() === null;
    }

    /** Əməliyyatların müəllifi və icazə yoxlaması üçün istifadəçi. */
    public function effectiveUser(): ?User
    {
        return $this->active() ? $this->operator() : auth()->user();
    }

    public function effectiveId(): ?int
    {
        return $this->effectiveUser()?->id;
    }

    /** Frontend üçün vəziyyət. */
    public function state(): array
    {
        $tenant = $this->tenancy->tenant();
        $op = $this->active() ? $this->operator() : null;

        return [
            'active' => $this->active(),
            'locked' => $this->locked(),
            'operator' => $op ? ['id' => $op->id, 'name' => $op->name, 'role' => $op->roleLabel()] : null,
            'canPay' => (bool) $this->effectiveUser()?->hasScope('PAYMENTS'),
            // Stok qalığı (POS kartlarındakı porsiya sayı) yalnız admin və kassirə — terminalda PIN ilə açan işçiyə görə
            'canSeeStock' => (bool) $this->effectiveUser()?->canSeeStockAlerts(),
            'device' => ($d = $this->device()) ? ['id' => $d->id, 'name' => $d->name, 'branch' => $d->branch?->name, 'branch_id' => $d->branch_id] : null,
            'lockTimeout' => (int) $tenant?->terminalSetting('lock_timeout'),
            'lockAfterOrder' => (bool) $tenant?->terminalSetting('lock_after_order'),
            'images' => $tenant?->screensaverUrls() ?? [],
            'store' => ['name' => $tenant?->name, 'logo' => $tenant?->logo ? Storage::disk('public')->url($tenant->logo) : null],
            'routes' => [
                'unlock' => route('pos.terminal.unlock'),
                'lock' => route('pos.terminal.lock'),
                'enter' => route('pos.terminal.enter'),
                'exit' => route('pos.terminal.exit'),
                'ping' => route('pos.terminal.ping'),
            ],
        ];
    }
}
