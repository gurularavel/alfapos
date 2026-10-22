<x-layouts.app title="Planlar">
    <x-page-header title="Planlar" subtitle="Abunəlik planları və limitlər.">
        <a href="{{ route('admin.plans.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Yeni plan</a>
    </x-page-header>
    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Plan</th><th class="text-right">Qiymət</th><th>İstifadəçi</th><th>Menyu</th><th>Masa</th><th>Biznes</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($plans as $p)
                <tr>
                    <td class="font-bold">{{ $p->name }}</td>
                    <td class="text-right">{{ $p->price }} {{ $p->currency }}/{{ $p->interval === 'year' ? 'il' : 'ay' }}</td>
                    <td>{{ $p->max_users ?? '∞' }}</td><td>{{ $p->max_menu_items ?? '∞' }}</td><td>{{ $p->max_tables ?? '∞' }}</td>
                    <td>{{ $p->tenants_count }}</td>
                    <td>{!! $p->is_active ? '<span class="badge-green">Aktiv</span>' : '<span class="badge-gray">Gizli</span>' !!}</td>
                    <td class="text-right whitespace-nowrap">
                        <a href="{{ route('admin.plans.edit', $p) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="size-4" /></a>
                        @unless ($p->tenants_count)
                            <form method="POST" action="{{ route('admin.plans.destroy', $p) }}" class="inline" onsubmit="return confirm('Silinsin?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button></form>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
