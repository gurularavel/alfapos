<x-layouts.app title="Vergi və servis haqqı">
<div x-data="{ edit: null }">
    <x-page-header title="Vergi və servis haqqı" subtitle="Vergini və servis haqqını buradan açıb-söndürün, dərəcələri idarə edin.">
        <a href="{{ route('settings.index') }}" class="btn-secondary">← Ayarlar</a>
    </x-page-header>

    <form method="POST" action="{{ route('settings.charges.update') }}" class="mb-6 grid gap-4 lg:grid-cols-2"
          x-data="{ tax: {{ $store->is_tax_enabled ? 'true' : 'false' }}, svc: {{ $store->is_service_charge_enabled ? 'true' : 'false' }} }">
        @csrf @method('PUT')
        <div class="card card-pad">
            <label class="flex cursor-pointer items-start justify-between gap-4">
                <span>
                    <span class="block text-lg font-extrabold">Vergilər</span>
                    <span class="text-sm text-zinc-500">Söndürüləndə yeni sifarişlərdə vergi hesablanmır, qəbzdə və POS-da vergi sətri görünmür. Məhsul qiymətləri dəyişmir.</span>
                </span>
                <input type="checkbox" name="is_tax_enabled" value="1" x-model="tax" class="mt-1 size-6 shrink-0 rounded text-brand-500">
            </label>
            <p class="mt-3 text-sm font-semibold" :class="tax ? 'text-brand-700' : 'text-zinc-500'" x-text="tax ? 'Aktiv — məhsula təyin olunmuş vergi tətbiq olunur' : 'Söndürülüb — vergisiz satış'"></p>
        </div>
        <div class="card card-pad">
            <label class="flex cursor-pointer items-start justify-between gap-4">
                <span>
                    <span class="block text-lg font-extrabold">Servis haqqı</span>
                    <span class="text-sm text-zinc-500">Endirimdən sonrakı məbləğin faizi kimi əlavə olunur. Kassir POS-da konkret sifariş üçün ləğv edə bilər.</span>
                </span>
                <input type="checkbox" name="is_service_charge_enabled" value="1" x-model="svc" class="mt-1 size-6 shrink-0 rounded text-brand-500">
            </label>
            <div class="mt-3 grid gap-3 sm:grid-cols-2" :class="!svc && 'pointer-events-none opacity-40'">
                <div>
                    <label class="label">Faiz</label>
                    <div class="flex items-center gap-2"><input type="number" name="service_charge" step="0.01" min="0" max="100" value="{{ old('service_charge', (float) $store->service_charge) }}" class="input w-28"> <span class="text-zinc-500">%</span></div>
                </div>
                <label class="flex items-start gap-2 pt-7 text-sm">
                    <input type="checkbox" name="service_charge_dinein_only" value="1" @checked($store->service_charge_dinein_only) class="mt-0.5 rounded text-brand-500">
                    <span>Yalnız zalda<br><span class="text-xs text-zinc-500">Özü aparan və çatdırılmaya tətbiq olunmur</span></span>
                </label>
            </div>
        </div>
        <div class="lg:col-span-2"><button class="btn-primary">Yadda saxla</button></div>
    </form>

    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-extrabold">Vergi dərəcələri <span class="text-sm font-normal text-zinc-500">— hər menyu məhsuluna bir vergi təyin olunur</span></h2>
        <button class="btn-secondary" @click="edit = { id: null, title: '', rate: 18, type: 'inclusive' }"><x-icon name="plus" class="size-4" /> Yeni vergi</button>
    </div>
    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Ad</th><th>Dərəcə</th><th>Tip</th><th></th></tr></thead>
            <tbody>
            @forelse ($taxes as $t)
                <tr>
                    <td class="font-bold">{{ $t->title }}</td>
                    <td>{{ rtrim(rtrim($t->rate, '0'), '.') }}%</td>
                    <td>{!! $t->type === 'inclusive' ? '<span class="badge-blue">Qiymətə daxil</span>' : '<span class="badge-amber">Üstə gəlir</span>' !!}</td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn-ghost btn-sm" @click="edit = @js(['id' => $t->id, 'title' => $t->title, 'rate' => (float) $t->rate, 'type' => $t->type])"><x-icon name="edit" class="size-4" /></button>
                        <form method="POST" action="{{ route('settings.taxes.destroy', $t) }}" class="inline" onsubmit="return confirm('Silinsin?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty icon="percent" title="Vergi yoxdur" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-modal show="edit" close="edit = null" title="Vergi" width="max-w-md">
        <template x-if="edit"><form method="POST" :action="edit.id ? @js(url('dashboard/settings/taxes')) + '/' + edit.id : @js(route('settings.taxes.store'))" id="tax-form" class="grid gap-3 sm:grid-cols-2">
            @csrf
            <template x-if="edit.id"><input type="hidden" name="_method" value="PUT"></template>
            <div class="sm:col-span-2"><label class="label">Ad *</label><input name="title" x-model="edit.title" class="input" required placeholder="ƏDV 18%"></div>
            <div><label class="label">Dərəcə (%) *</label><input type="number" step="0.01" min="0" max="100" name="rate" x-model="edit.rate" class="input" required></div>
            <div><label class="label">Tip</label><select name="type" x-model="edit.type" class="input"><option value="inclusive">Qiymətə daxil</option><option value="exclusive">Üstə gəlir</option></select></div>
        </form></template>
        <x-slot:footer><button class="btn-secondary" @click="edit = null">Bağla</button><button class="btn-primary" form="tax-form">Saxla</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
