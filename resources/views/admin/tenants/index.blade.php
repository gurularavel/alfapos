<x-layouts.app title="Bizneslər">
    <x-page-header title="Bizneslər" subtitle="Platformadakı bütün restoran və kafelər.">
        <a href="{{ route('admin.tenants.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Yeni biznes</a>
    </x-page-header>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['Cəmi biznes', $stats['total']], ['Aktiv abunə', $stats['active']], ['Sınaqda', $stats['trial']], ['Aktiv abunə dəyəri', number_format($stats['mrr'], 2).' AZN']] as [$label, $value])
            <div class="card card-pad"><p class="text-sm font-semibold text-zinc-500">{{ $label }}</p><p class="text-2xl font-black">{{ $value }}</p></div>
        @endforeach
    </div>

    <form class="card mb-4 grid gap-2 p-3 sm:grid-cols-[1fr_12rem_auto]">
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Ad və ya email">
        <select name="status" class="input"><option value="">Bütün statuslar</option>@foreach (['trial' => 'Sınaq', 'active' => 'Aktiv', 'expired' => 'Bitib', 'suspended' => 'Dayandırılıb'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select>
        <button class="btn-primary">Filtr</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Biznes</th><th>Plan</th><th>Status</th><th>Bitmə</th><th>İstifadəçi</th><th class="text-right">Son 30 gün dövriyyə</th><th>Qeydiyyat</th><th></th></tr></thead>
            <tbody>
            @forelse ($tenants as $t)
                <tr>
                    <td><p class="font-bold">{{ $t->name }}</p><p class="text-xs text-zinc-500">{{ $t->email }}</p></td>
                    <td>{{ $t->plan?->name ?? '—' }}</td>
                    <td>
                        @if ($t->hasActiveSubscription())<span class="{{ $t->status === 'trial' ? 'badge-amber' : 'badge-green' }}">{{ $t->status === 'trial' ? 'Sınaq' : 'Aktiv' }}</span>
                        @else<span class="badge-red">{{ $t->status === 'suspended' ? 'Dayandırılıb' : 'Bitib' }}</span>@endif
                    </td>
                    <td class="text-zinc-500">{{ $t->expiresAt()?->format('d.m.Y') ?? '—' }}</td>
                    <td>{{ $t->users_count }}</td>
                    <td class="text-right font-semibold">{{ number_format($t->revenue_30d, 2) }} {{ $t->currency }}</td>
                    <td class="text-zinc-500">{{ $t->created_at->format('d.m.Y') }}</td>
                    <td class="text-right"><a href="{{ route('admin.tenants.edit', $t) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="size-4" /></a></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="building" title="Biznes yoxdur" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $tenants->links() }}</div>
</x-layouts.app>
