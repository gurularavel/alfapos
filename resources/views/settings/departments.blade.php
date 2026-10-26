<x-layouts.app title="Şöbələr">
<div x-data="{ edit: null }">
    <x-page-header title="Şöbələr" subtitle="Mətbəx, bar və s.: sifariş göndəriləndə hər şöbəyə öz çeki çıxır (məs. çay, pivə — bara; noxud — mətbəxə).">
        <a href="{{ route('settings.index') }}" class="btn-secondary">← Ayarlar</a>
        <button class="btn-primary" @click="edit = { id: null, name: '', print_at: 'pos', sort: {{ $departments->count() + 1 }}, is_default: false, category_ids: [] }"><x-icon name="plus" class="size-4" /> Yeni şöbə</button>
    </x-page-header>

    <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        Məhsulun şöbəsi kateqoriyadan gəlir; ayrıca məhsulu başqa şöbəyə vermək üçün Menyu → məhsulu redaktə et → "Şöbə".
        "Şöbənin öz cihazında" seçilibsə, həmin şöbədəki kompüter/planşetdə <a href="{{ route('kitchen') }}" class="font-semibold underline">Mətbəx ekranını</a> açıb şöbəni seçin və "Bu cihaz ... çeklərini çap edir" işarələyin.
    </div>

    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Sıra</th><th>Şöbə</th><th>Çap yeri</th><th>Kateqoriyalar</th><th></th></tr></thead>
            <tbody>
            @foreach ($departments as $d)
                @php($cats = $categories->filter(fn ($c) => $c->department_id === $d->id || ($d->is_default && $c->department_id === null)))
                <tr>
                    <td class="w-16 text-zinc-500">{{ $d->sort }}</td>
                    <td class="font-bold">{{ $d->name }} @if ($d->is_default)<span class="badge-green">əsas</span>@endif</td>
                    <td>{{ \App\Models\Department::PRINT_AT[$d->print_at] ?? $d->print_at }}</td>
                    <td class="text-sm text-zinc-600">{{ $cats->pluck('title')->implode(', ') ?: '—' }}</td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn-ghost btn-sm" @click="edit = @js(['id' => $d->id, 'name' => $d->name, 'print_at' => $d->print_at, 'sort' => $d->sort, 'is_default' => $d->is_default, 'category_ids' => $categories->where('department_id', $d->id)->pluck('id')->values()])"><x-icon name="edit" class="size-4" /></button>
                        @unless ($d->is_default)
                            <form method="POST" action="{{ route('settings.departments.destroy', $d) }}" class="inline" onsubmit="return confirm('Şöbə silinsin? Onun kateqoriya və məhsulları əsas şöbəyə keçəcək.')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button></form>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <x-modal show="edit" close="edit = null" title="Şöbə" width="max-w-lg">
        <template x-if="edit"><form method="POST" :action="edit.id ? @js(url('dashboard/settings/departments')) + '/' + edit.id : @js(route('settings.departments.store'))" id="dep-form" class="space-y-4">
            @csrf
            <template x-if="edit.id"><input type="hidden" name="_method" value="PUT"></template>
            <div class="grid gap-3 sm:grid-cols-[1fr_6rem]">
                <div><label class="label">Ad *</label><input name="name" x-model="edit.name" class="input" maxlength="60" placeholder="Məs. Bar" required></div>
                <div><label class="label">Sıra</label><input type="number" min="0" name="sort" x-model="edit.sort" class="input"></div>
            </div>
            <div>
                <p class="label">Çek harada çap olunsun?</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach (\App\Models\Department::PRINT_AT as $value => $label)
                        <label class="flex cursor-pointer items-start gap-2 rounded-xl border p-3 text-sm" :class="edit.print_at === @js($value) ? 'border-brand-500 bg-brand-50' : 'border-zinc-200'">
                            <input type="radio" name="print_at" value="{{ $value }}" x-model="edit.print_at" class="mt-0.5 text-brand-500">
                            <span><b>{{ $label }}</b><br><span class="text-zinc-500">{{ $value === 'pos' ? 'Sifariş göndərən kassa/planşet bu şöbənin çekini ayrıca çap edir.' : 'Şöbədəki cihaz (Mətbəx ekranı) çeki avtomatik çap edir; kassa çap etmir.' }}</span></span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <p class="label">Kateqoriyalar</p>
                <div class="grid max-h-48 gap-1.5 overflow-y-auto sm:grid-cols-2">
                    @foreach ($categories as $c)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="category_ids[]" value="{{ $c->id }}" x-model.number="edit.category_ids" class="rounded text-brand-500"> {{ $c->title }}</label>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-zinc-500">Heç bir şöbəyə verilməyən kateqoriyalar əsas şöbəyə aiddir.</p>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_default" value="0"><input type="checkbox" name="is_default" value="1" x-model="edit.is_default" class="rounded text-brand-500"> Əsas şöbə (şöbəsi seçilməyən məhsullar bura düşür)</label>
        </form></template>
        <x-slot:footer><button class="btn-secondary" @click="edit = null">Bağla</button><button class="btn-primary" form="dep-form">Saxla</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
