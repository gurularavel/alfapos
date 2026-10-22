<x-layouts.app :title="$tenant->exists ? $tenant->name : 'Yeni biznes'">
    <x-page-header :title="$tenant->exists ? $tenant->name : 'Yeni biznes'"><a href="{{ route('admin.tenants.index') }}" class="btn-secondary">← Bizneslər</a></x-page-header>

    @if (! $tenant->exists)
        <form method="POST" action="{{ route('admin.tenants.store') }}" class="card card-pad grid max-w-2xl gap-4 sm:grid-cols-2">
            @csrf
            <div class="sm:col-span-2"><label class="label">Biznesin adı *</label><input name="name" value="{{ old('name') }}" class="input" required></div>
            <div><label class="label">Sahibin adı *</label><input name="owner_name" value="{{ old('owner_name') }}" class="input" required></div>
            <div><label class="label">Telefon</label><input name="phone" value="{{ old('phone') }}" class="input"></div>
            <div><label class="label">Email (login) *</label><input name="email" type="email" value="{{ old('email') }}" class="input" required></div>
            <div><label class="label">Parol *</label><input name="password" type="password" class="input" required autocomplete="new-password"></div>
            <div class="sm:col-span-2"><button class="btn-primary">Yarat ({{ \App\Services\TenantProvisioner::TRIAL_DAYS }} gün sınaq)</button></div>
        </form>
    @else
        <div class="grid gap-4 xl:grid-cols-3">
            <form method="POST" action="{{ route('admin.tenants.update', $tenant) }}" class="card card-pad space-y-4 xl:col-span-2">
                @csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="label">Ad</label><input name="name" value="{{ old('name', $tenant->name) }}" class="input" required></div>
                    <div><label class="label">Plan</label><select name="plan_id" class="input"><option value="">—</option>@foreach ($plans as $p)<option value="{{ $p->id }}" @selected($tenant->plan_id === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                    <div><label class="label">Status</label><select name="status" class="input">@foreach (['trial' => 'Sınaq', 'active' => 'Aktiv', 'expired' => 'Bitib', 'suspended' => 'Dayandırılıb'] as $k => $v)<option value="{{ $k }}" @selected($tenant->status === $k)>{{ $v }}</option>@endforeach</select></div>
                    <div><label class="label">Sınaq bitir</label><input type="date" name="trial_ends_at" value="{{ $tenant->trial_ends_at?->toDateString() }}" class="input"></div>
                    <div><label class="label">Abunə bitir</label><input type="date" name="subscription_ends_at" value="{{ $tenant->subscription_ends_at?->toDateString() }}" class="input"></div>
                </div>
                <button class="btn-primary">Yadda saxla</button>

                <hr class="border-zinc-100">
                <p class="font-extrabold">İstifadəçilər</p>
                <table class="table-base">
                    <tbody>@foreach ($tenant->users as $u)<tr><td class="font-semibold">{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->isAdmin() ? 'Admin' : 'İşçi' }}</td><td>{{ $u->is_active ? 'Aktiv' : 'Deaktiv' }}</td></tr>@endforeach</tbody>
                </table>
            </form>

            <div class="space-y-4">
                <form method="POST" action="{{ route('admin.tenants.extend', $tenant) }}" class="card card-pad space-y-3">
                    @csrf
                    <p class="font-extrabold">Ödəniş qeyd et / uzat</p>
                    <div><label class="label">Plan</label><select name="plan_id" class="input">@foreach ($plans as $p)<option value="{{ $p->id }}" @selected($tenant->plan_id === $p->id)>{{ $p->name }} — {{ $p->price }} {{ $p->currency }}</option>@endforeach</select></div>
                    <div><label class="label">Ay sayı</label><input type="number" name="months" min="1" max="36" value="1" class="input"></div>
                    <div><label class="label">İstinad (qəbz №)</label><input name="reference" class="input"></div>
                    <button class="btn-primary w-full">Abunəliyi uzat</button>
                </form>
                <div class="card card-pad">
                    <p class="mb-2 font-extrabold">Ödəniş tarixçəsi</p>
                    @forelse ($tenant->subscriptions->sortByDesc('starts_at') as $s)
                        <p class="flex justify-between border-b border-zinc-100 py-1.5 text-sm"><span>{{ $s->plan?->name }} · {{ $s->starts_at->format('d.m.y') }}–{{ $s->ends_at->format('d.m.y') }}</span><span class="font-bold">{{ $s->amount }}</span></p>
                    @empty
                        <p class="text-sm text-zinc-500">Ödəniş yoxdur.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</x-layouts.app>
