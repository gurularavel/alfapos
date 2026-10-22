<x-layouts.app title="Masalar">
<div x-data="{ edit: null }">
    <x-page-header title="Masalar" subtitle="Hər masanın öz QR kodu var — çap edib masaya qoyun.">
        <a href="{{ route('settings.index') }}" class="btn-secondary">← Ayarlar</a>
        <button class="btn-primary" @click="edit = { id: null, title: '', floor: '', seating_capacity: 4, deposit: 0 }"><x-icon name="plus" class="size-4" /> Yeni masa</button>
    </x-page-header>

    @forelse ($tables->groupBy(fn ($t) => $t->floor ?: 'Ümumi') as $floor => $group)
        <h2 class="mt-2 mb-3 font-extrabold text-zinc-600">{{ $floor }}</h2>
        <div class="mb-6 grid gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
            @foreach ($group as $t)
                <div class="card card-pad text-center">
                    <div class="mx-auto mb-2 flex size-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="table" /></div>
                    <p class="font-extrabold">{{ $t->title }}</p>
                    <p class="text-xs text-zinc-500">{{ $t->seating_capacity }} nəfər</p>
                    @if ($t->hasDeposit())<p class="mt-1"><span class="badge-amber">Depozit {{ money($t->deposit) }}</span></p>@endif
                    <div class="mt-3 flex justify-center gap-1">
                        <a href="{{ route('settings.tables.qr', $t) }}" target="_blank" class="btn-ghost btn-sm" title="QR kod"><x-icon name="qr" class="size-4" /></a>
                        <button class="btn-ghost btn-sm" @click="edit = @js(['id' => $t->id, 'title' => $t->title, 'floor' => $t->floor, 'seating_capacity' => $t->seating_capacity, 'deposit' => (float) $t->deposit])"><x-icon name="edit" class="size-4" /></button>
                        <form method="POST" action="{{ route('settings.tables.destroy', $t) }}" onsubmit="return confirm('Silinsin?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button></form>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="card"><x-empty icon="table" title="Masa yoxdur">Zal sifarişləri və QR menyu üçün masalar əlavə edin.</x-empty></div>
    @endforelse

    <x-modal show="edit" close="edit = null" title="Masa" width="max-w-md">
        <template x-if="edit"><form method="POST" :action="edit.id ? @js(url('dashboard/settings/tables')) + '/' + edit.id : @js(route('settings.tables.store'))" id="table-form" class="grid gap-3 sm:grid-cols-2">
            @csrf
            <template x-if="edit.id"><input type="hidden" name="_method" value="PUT"></template>
            <div class="sm:col-span-2"><label class="label">Ad *</label><input name="title" x-model="edit.title" class="input" required placeholder="Masa 1"></div>
            <div><label class="label">Mərtəbə / zona</label><input name="floor" x-model="edit.floor" class="input" placeholder="Zal, Teras..."></div>
            <div><label class="label">Tutum *</label><input type="number" min="1" name="seating_capacity" x-model="edit.seating_capacity" class="input" required></div>
            <div class="sm:col-span-2">
                <label class="label">Depozit (minimum məbləğ) <span class="font-normal text-zinc-400">— kabinet / VIP üçün</span></label>
                <input type="number" step="0.01" min="0" name="deposit" x-model="edit.deposit" class="input" placeholder="0 = depozit yoxdur">
                <p class="mt-1 text-xs text-zinc-500">Hesab bu məbləğdən az olarsa, fərq qəbzə "Depozitə qədər fərq" kimi əlavə olunur.</p>
            </div>
        </form></template>
        <x-slot:footer><button class="btn-secondary" @click="edit = null">Bağla</button><button class="btn-primary" form="table-form">Saxla</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
