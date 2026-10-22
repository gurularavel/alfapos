<x-layouts.app title="Müştərilər">
<div x-data="{ edit: null, blank: { id: null, name: '', phone: '', email: '', birth_date: '', gender: '', is_member: false } }">
    <x-page-header title="Müştərilər" subtitle="Müştəri bazası və xərcləmə tarixçəsi.">
        <a href="{{ route('customers.export') }}" class="btn-secondary"><x-icon name="download" class="size-4" /> CSV</a>
        <button class="btn-primary" @click="edit = { ...blank }"><x-icon name="plus" class="size-4" /> Yeni müştəri</button>
    </x-page-header>

    <form class="card mb-4 flex gap-2 p-3">
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Ad, telefon və ya email">
        <button class="btn-primary"><x-icon name="search" class="size-4" /></button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Ad</th><th>Telefon</th><th>Email</th><th>Doğum tarixi</th><th class="text-right">Çek</th><th class="text-right">Xərc</th><th></th></tr></thead>
            <tbody>
            @forelse ($customers as $c)
                <tr>
                    <td class="font-bold"><a href="{{ route('customers.show', $c) }}" class="hover:text-brand-600">{{ $c->name }}</a> @if ($c->is_member)<span class="badge-green">Üzv</span>@endif</td>
                    <td>{{ $c->phone }}</td>
                    <td class="text-zinc-500">{{ $c->email }}</td>
                    <td class="text-zinc-500">{{ $c->birth_date?->format('d.m.Y') }}</td>
                    <td class="text-right">{{ $c->invoices_count }}</td>
                    <td class="text-right font-bold">{{ money($c->invoices_sum_total ?? 0) }}</td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn-ghost btn-sm" @click="edit = @js(['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'email' => $c->email, 'birth_date' => $c->birth_date?->format('Y-m-d'), 'gender' => $c->gender, 'is_member' => $c->is_member])"><x-icon name="edit" class="size-4" /></button>
                        <form method="POST" action="{{ route('customers.destroy', $c) }}" class="inline" onsubmit="return confirm('Silinsin?')">@csrf @method('DELETE')
                            <button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty icon="users" title="Müştəri yoxdur">POS-da müştəri əlavə edə və ya buradan yarada bilərsiniz.</x-empty></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>

    <x-modal show="edit" close="edit = null" title="Müştəri">
        <template x-if="edit"><form method="POST" :action="edit?.id ? @js(url('dashboard/customers')) + '/' + edit.id : @js(route('customers.store'))" id="customer-form" class="grid gap-3 sm:grid-cols-2">
            @csrf
            <template x-if="edit?.id"><input type="hidden" name="_method" value="PUT"></template>
            <div><label class="label">Telefon *</label><input name="phone" x-model="edit.phone" class="input" required></div>
            <div><label class="label">Ad *</label><input name="name" x-model="edit.name" class="input" required></div>
            <div><label class="label">Email</label><input name="email" type="email" x-model="edit.email" class="input"></div>
            <div><label class="label">Doğum tarixi</label><input name="birth_date" type="date" x-model="edit.birth_date" class="input"></div>
            <div><label class="label">Cins</label>
                <select name="gender" x-model="edit.gender" class="input"><option value="">—</option><option value="female">Qadın</option><option value="male">Kişi</option><option value="other">Digər</option></select>
            </div>
            <label class="mt-7 flex items-center gap-2 text-sm"><input type="hidden" name="is_member" value="0"><input type="checkbox" name="is_member" value="1" x-model="edit.is_member" class="rounded text-brand-500"> Üzv (loyallıq)</label>
        </form></template>
        <x-slot:footer>
            <button class="btn-secondary" @click="edit = null">Bağla</button>
            <button class="btn-primary" form="customer-form">Saxla</button>
        </x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
