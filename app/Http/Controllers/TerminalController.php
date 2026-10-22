<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\PosTerminal;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TerminalController extends Controller
{
    public function __construct(protected PosTerminal $terminal, protected Tenancy $tenancy) {}

    /** Cihazı terminal rejiminə keçirir. Yalnız admin və ya ayarlara icazəsi olan hesab. */
    public function enter(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->hasScope('SETTINGS'), 403, 'Terminal rejimini yalnız admin aça bilər.');

        $hasPins = User::where('tenant_id', $this->tenancy->id())->whereNotNull('pin_hash')->where('is_active', true)->exists();
        abort_unless($hasPins, 422, 'Əvvəlcə İstifadəçilər bölməsində işçilərə PIN təyin edin.');

        $this->terminal->enter();
        AuditLog::record('terminal.enter');

        return response()->json(['message' => 'Terminal rejimi aktivdir.', 'state' => $this->terminal->state()]);
    }

    /** Terminal rejimindən çıxış — admin (və ya ayarlar icazəli işçi) PIN-i ilə. */
    public function exit(Request $request)
    {
        $user = $this->pinUser($request);
        if (! $user->isAdmin() && ! $user->hasScope('SETTINGS')) {
            throw ValidationException::withMessages(['pin' => 'Terminaldan çıxmaq üçün admin PIN-i lazımdır.']);
        }

        $this->terminal->exit();
        AuditLog::record('terminal.exit', null, ['by' => $user->name]);

        return response()->json(['message' => 'Terminal rejimi bağlandı.']);
    }

    public function unlock(Request $request)
    {
        $user = $this->pinUser($request);
        if (! $user->hasScope('POS') && ! $user->hasScope('ORDERS')) {
            throw ValidationException::withMessages(['pin' => 'Bu işçinin POS-a icazəsi yoxdur.']);
        }

        $this->terminal->unlock($user);

        return response()->json(['message' => 'Xoş gəldiniz, '.$user->name.'!', 'state' => $this->terminal->state()]);
    }

    public function lock()
    {
        $this->terminal->lock();

        return response()->json(['state' => $this->terminal->state()]);
    }

    /** İşçi ekranla işləyərkən sessiyanın "son aktivlik" vaxtını yeniləyir (operator() bunu edir). */
    public function ping()
    {
        return response()->json(['locked' => $this->terminal->locked()]);
    }

    protected function pinUser(Request $request): User
    {
        $data = $request->validate(['pin' => ['required', 'digits:4']]);
        $user = User::findByPin($this->tenancy->id(), $data['pin']);
        if (! $user) {
            throw ValidationException::withMessages(['pin' => 'PIN yanlışdır.']);
        }

        return $user;
    }
}
