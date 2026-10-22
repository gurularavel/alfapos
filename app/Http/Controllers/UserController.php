<?php

namespace App\Http\Controllers;

use App\Models\StoreTable;
use App\Models\Terminal;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index()
    {
        return view('users.index', [
            'users' => User::where('tenant_id', $this->tenancy->id())->with('tables:id', 'terminals:id')->orderBy('name')->get(),
            'tables' => StoreTable::orderBy('title')->get(),
            'terminals' => Terminal::with('branch:id,name')->where('is_active', true)->orderBy('branch_id')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $this->tenancy->tenant();
        $limit = $tenant->plan?->max_users;
        if ($limit && $tenant->users()->count() >= $limit) {
            return back()->withErrors(['email' => "Planınız maksimum {$limit} istifadəçiyə icazə verir."]);
        }

        $data = $this->validated($request);
        // Parolsuz işçi panelə daxil ola bilməz, yalnız PIN ilə terminalda işləyir
        $data['password'] ??= Str::random(40);
        $user = new User($data + ['tenant_id' => $tenant->id]);
        $this->applyPin($request, $user);
        $user->save();
        $user->tables()->sync($this->tableIds($request));
        $user->terminals()->sync(Terminal::whereIn('id', (array) $request->input('terminal_ids', []))->pluck('id'));

        return back()->with('success', 'İstifadəçi əlavə edildi.');
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTenant($user);
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        // Admin özünü deaktiv edə və ya rolunu sala bilməz
        if ($user->is(auth()->user())) {
            $data['role'] = 'admin';
            $data['is_active'] = true;
        }

        $user->fill($data);
        $this->applyPin($request, $user);
        $user->save();
        $user->tables()->sync($this->tableIds($request));
        $user->terminals()->sync(Terminal::whereIn('id', (array) $request->input('terminal_ids', []))->pluck('id'));

        return back()->with('success', 'İstifadəçi yeniləndi.');
    }

    public function destroy(User $user)
    {
        $this->authorizeTenant($user);
        abort_if($user->is(auth()->user()), 422, 'Özünüzü silə bilməzsiniz.');
        $user->delete();

        return back()->with('success', 'İstifadəçi silindi.');
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $isAdmin = $request->input('role') === 'admin';
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [$isAdmin ? 'required' : 'nullable', 'email', 'max:190', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:60'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => [Rule::in(array_keys(User::SCOPES))],
            'is_active' => ['boolean'],
            'password' => [$isAdmin && ! $user?->email ? 'required' : 'nullable', Password::min(8)],
            'pin' => ['nullable', 'digits:4'],
        ], [
            'email.required' => 'Admin üçün email (login) mütləqdir.',
            'pin.digits' => 'PIN 4 rəqəmdən ibarət olmalıdır.',
        ]);
        $data['scopes'] = array_values($data['scopes'] ?? []);
        $data['is_active'] = $request->boolean('is_active');
        unset($data['pin']);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }

    /** PIN-i təyin edir/silir; eyni biznesdə iki işçinin PIN-i eyni ola bilməz. */
    protected function applyPin(Request $request, User $user): void
    {
        if ($request->boolean('remove_pin')) {
            $user->setPin(null);

            return;
        }
        $pin = $request->input('pin');
        if (! $pin) {
            return;
        }

        $taken = User::where('tenant_id', $user->tenant_id)
            ->where('pin_hash', User::pinHash($user->tenant_id, $pin))
            ->when($user->exists, fn ($q) => $q->whereKeyNot($user->id))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['pin' => 'Bu PIN artıq başqa işçiyə verilib.']);
        }

        $user->setPin($pin);
    }

    protected function tableIds(Request $request): array
    {
        return StoreTable::whereIn('id', (array) $request->input('table_ids', []))->pluck('id')->all();
    }

    protected function authorizeTenant(User $user): void
    {
        abort_unless($user->tenant_id === $this->tenancy->id(), 404);
    }
}
