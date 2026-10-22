<?php

namespace App\Http\Controllers;

use App\Models\StoreTable;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index()
    {
        return view('users.index', [
            'users' => User::where('tenant_id', $this->tenancy->id())->with('tables:id')->orderBy('name')->get(),
            'tables' => StoreTable::orderBy('title')->get(),
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
        $user = User::create($data + ['tenant_id' => $tenant->id]);
        $user->tables()->sync($this->tableIds($request));

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

        $user->update($data);
        $user->tables()->sync($this->tableIds($request));

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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:60'],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => [Rule::in(array_keys(User::SCOPES))],
            'is_active' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
        ]);
        $data['scopes'] = array_values($data['scopes'] ?? []);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
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
