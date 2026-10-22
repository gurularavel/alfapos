<x-layouts.app title="Ödəniş növləri">
<div x-data="{ edit: null }">
    <x-page-header title="Ödəniş növləri">
        <a href="{{ route('settings.index') }}" class="btn-secondary">← Ayarlar</a>
        <button class="btn-primary" @click="edit = { id: null, title: '', icon: 'cash', is_active: true }"><x-icon name="plus" class="size-4" /> Yeni ödəniş növü</button>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @forelse ($paymentTypes as $p)
            <div class="card card-pad flex items-center gap-3">
                <div class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon :name="$p->icon" /></div>
                <div class="min-w-0 flex-1">
                    <p class="font-extrabold">{{ $p->title }}</p>
                    {!! $p->is_active ? '<span class="badge-green">Aktiv</span>' : '<span class="badge-gray">Deaktiv</span>' !!}
                </div>
                <button class="btn-ghost btn-sm" @click="edit = @js($p->only(['id', 'title', 'icon', 'is_active']))"><x-icon name="edit" class="size-4" /></button>
                <form method="POST" action="{{ route('settings.payment-types.destroy', $p) }}" onsubmit="return confirm('Silinsin?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button></form>
            </div>
        @empty
            <div class="card sm:col-span-2 xl:col-span-4"><x-empty icon="card" title="Ödəniş növü yoxdur" /></div>
        @endforelse
    </div>

    <x-modal show="edit" close="edit = null" title="Ödəniş növü" width="max-w-md">
        <template x-if="edit"><form method="POST" :action="edit.id ? @js(url('dashboard/settings/payment-types')) + '/' + edit.id : @js(route('settings.payment-types.store'))" id="pt-form" class="space-y-3">
            @csrf
            <template x-if="edit.id"><input type="hidden" name="_method" value="PUT"></template>
            <div><label class="label">Ad *</label><input name="title" x-model="edit.title" class="input" required></div>
            <div><label class="label">Tip</label><select name="icon" x-model="edit.icon" class="input">@foreach ($icons as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" x-model="edit.is_active" class="rounded text-brand-500"> Aktiv</label>
        </form></template>
        <x-slot:footer><button class="btn-secondary" @click="edit = null">Bağla</button><button class="btn-primary" form="pt-form">Saxla</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
