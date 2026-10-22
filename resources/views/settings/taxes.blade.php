<x-layouts.app title="Vergilər">
<div x-data="{ edit: null }">
    <x-page-header title="Vergilər" subtitle="Daxil (qiymətin içində) və ya üstə gələn vergi. Hər menyu məhsuluna bir vergi təyin olunur.">
        <a href="{{ route('settings.index') }}" class="btn-secondary">← Ayarlar</a>
        <button class="btn-primary" @click="edit = { id: null, title: '', rate: 18, type: 'inclusive' }"><x-icon name="plus" class="size-4" /> Yeni vergi</button>
    </x-page-header>

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
