<x-layouts.app title="Filiallar və terminallar">
<div x-data="{ branch: null, term: null }">
    <x-page-header title="Filiallar və terminallar" subtitle="Hər kassa/POS cihazı bir terminaldır: sifariş və çeklər hansı terminaldan vurulubsa hesabatlarda elə görünür.">
        <a href="{{ route('settings.index') }}" class="btn-secondary">← Ayarlar</a>
        <button class="btn-secondary" @click="branch = { id: null, name: '', address: '', phone: '', is_active: true }"><x-icon name="plus" class="size-4" /> Yeni filial</button>
    </x-page-header>

    {{-- Bu cihaz --}}
    <form method="POST" action="{{ route('settings.device.bind') }}" @class(['card card-pad mb-6 flex flex-wrap items-center gap-4 border-l-4', 'border-l-brand-500' => $device, 'border-l-amber-400' => ! $device])>
        @csrf
        <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600"><x-icon name="pos" class="size-6" /></div>
        <div class="min-w-0 flex-1">
            <p class="font-extrabold">Bu cihaz</p>
            <p class="text-sm text-zinc-600">
                @if ($device) Terminal: <b>{{ $device->label() }}</b> — buradan vurulan sifariş və çeklər bu terminala yazılır.
                @else Bu cihaz heç bir terminala bağlı deyil. Kassa/POS kompüterində bu səhifəni açıb terminalı seçin.
                @endif
            </p>
        </div>
        <select name="terminal_id" class="input w-auto min-w-56">
            <option value="">— Terminala bağlı deyil —</option>
            @foreach ($branches as $b)
                <optgroup label="{{ $b->name }}">
                    @foreach ($b->terminals->where('is_active', true) as $t)<option value="{{ $t->id }}" @selected($device?->id === $t->id)>{{ $t->name }}</option>@endforeach
                </optgroup>
            @endforeach
        </select>
        <button class="btn-primary">Yadda saxla</button>
    </form>

    <div class="space-y-5">
        @foreach ($branches as $b)
            <section class="card overflow-hidden">
                <div class="flex flex-wrap items-center gap-3 border-b border-zinc-100 px-5 py-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-lg font-black">{{ $b->name }} @unless ($b->is_active)<span class="badge-gray">Deaktiv</span>@endunless</p>
                        <p class="text-sm text-zinc-500">{{ collect([$b->address, $b->phone])->filter()->implode(' · ') ?: 'Ünvan qeyd olunmayıb' }} · {{ $b->tables_count }} masa</p>
                    </div>
                    <button class="btn-secondary btn-sm" @click="term = { id: null, name: '', branch_id: {{ $b->id }}, is_active: true, user_ids: [] }"><x-icon name="plus" class="size-4" /> Terminal</button>
                    <button class="btn-ghost btn-sm" @click="branch = @js($b->only(['id', 'name', 'address', 'phone', 'is_active']))"><x-icon name="edit" class="size-4" /></button>
                    @if ($branches->count() > 1)
                        <form method="POST" action="{{ route('settings.branches.destroy', $b) }}" onsubmit="return confirm('Filial silinsin?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button></form>
                    @endif
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($b->terminals as $t)
                        <div @class(['rounded-2xl border p-4', 'border-brand-400 bg-brand-50' => $device?->id === $t->id, 'border-zinc-200' => $device?->id !== $t->id])>
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-extrabold">{{ $t->name }} @if ($device?->id === $t->id)<span class="badge-green">Bu cihaz</span>@endif @unless ($t->is_active)<span class="badge-gray">Deaktiv</span>@endunless</p>
                                    <p class="text-xs" @class(['text-brand-700' => $t->isOnline(), 'text-zinc-400' => ! $t->isOnline()])>
                                        <span class="mr-1 inline-block size-2 rounded-full {{ $t->isOnline() ? 'bg-brand-500' : 'bg-zinc-300' }}"></span>{{ $t->isOnline() ? 'İşləyir' : ($t->last_seen_at ? 'Son: '.$t->last_seen_at->diffForHumans() : 'Hələ qoşulmayıb') }}
                                    </p>
                                </div>
                                <div class="flex">
                                    <button class="btn-ghost btn-sm" @click="term = @js(['id' => $t->id, 'name' => $t->name, 'branch_id' => $t->branch_id, 'is_active' => $t->is_active, 'user_ids' => $t->users->pluck('id')])"><x-icon name="edit" class="size-4" /></button>
                                    <form method="POST" action="{{ route('settings.terminals.destroy', $t) }}" onsubmit="return confirm('Terminal silinsin? Köhnə çeklər qalır.')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button></form>
                                </div>
                            </div>
                            <p class="mt-2 text-sm text-zinc-600">
                                <span class="font-semibold">İşçilər:</span>
                                {{ $t->users->isEmpty() ? 'xüsusi təyin yoxdur (terminalı olmayan işçilər)' : $t->users->pluck('name')->implode(', ') }}
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">Terminal yoxdur.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    <div class="card card-pad mt-6 text-sm text-zinc-600">
        <p class="mb-1 font-extrabold text-ink">Necə işləyir?</p>
        <ol class="list-decimal space-y-1 pl-5">
            <li>Hər kassa/POS cihazında admin bu səhifəni açır və "Bu cihaz" bölməsində terminalı seçir — seçim cihazda yadda qalır.</li>
            <li>Bu cihazdan vurulan sifariş və çeklər terminala və onun filialına yazılır; POS, Masalar, Sifarişlər və Mətbəx ekranları yalnız həmin filialın masalarını/sifarişlərini göstərir.</li>
            <li>İşçini terminala bağlasanız, onun PIN-i yalnız o terminal(lar)da işləyir. Heç bir terminala bağlanmamış işçi bütün terminallarda işləyə bilər.</li>
            <li>Hesabatlarda filial və terminal üzrə filtr və "Terminallar üzrə satış" hesabatı var.</li>
        </ol>
    </div>

    <x-modal show="branch" close="branch = null" title="Filial" width="max-w-md">
        <template x-if="branch"><form method="POST" :action="branch.id ? @js(url('dashboard/settings/branches')) + '/' + branch.id : @js(route('settings.branches.store'))" id="branch-form" class="space-y-3">
            @csrf
            <template x-if="branch.id"><input type="hidden" name="_method" value="PUT"></template>
            <div><label class="label">Ad *</label><input name="name" x-model="branch.name" class="input" required placeholder="Məs. 28 May filialı"></div>
            <div><label class="label">Ünvan</label><input name="address" x-model="branch.address" class="input"></div>
            <div><label class="label">Telefon</label><input name="phone" x-model="branch.phone" class="input"></div>
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" x-model="branch.is_active" class="rounded text-brand-500"> Aktiv</label>
        </form></template>
        <x-slot:footer><button class="btn-secondary" @click="branch = null">Bağla</button><button class="btn-primary" form="branch-form">Saxla</button></x-slot:footer>
    </x-modal>

    <x-modal show="term" close="term = null" title="Terminal" width="max-w-lg">
        <template x-if="term"><form method="POST" :action="term.id ? @js(url('dashboard/settings/terminals')) + '/' + term.id : @js(route('settings.terminals.store'))" id="term-form" class="space-y-3">
            @csrf
            <template x-if="term.id"><input type="hidden" name="_method" value="PUT"></template>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label">Ad *</label><input name="name" x-model="term.name" class="input" required placeholder="Kassa 1, Bar, Teras..."></div>
                <div><label class="label">Filial *</label><select name="branch_id" x-model.number="term.branch_id" class="input">@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
            </div>
            <div>
                <p class="label">Bu terminalda işləyən işçilər <span class="font-normal text-zinc-400">— seçilənlərin PIN-i yalnız öz terminallarında işləyir</span></p>
                <div class="grid max-h-56 grid-cols-2 gap-2 overflow-y-auto">
                    @foreach ($staff as $u)
                        <label class="flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50"><input type="checkbox" name="user_ids[]" value="{{ $u->id }}" x-model.number="term.user_ids" class="rounded text-brand-500"> {{ $u->name }} <span class="text-xs text-zinc-400">{{ $u->roleLabel() }}</span></label>
                    @endforeach
                </div>
            </div>
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" x-model="term.is_active" class="rounded text-brand-500"> Aktiv</label>
        </form></template>
        <x-slot:footer><button class="btn-secondary" @click="term = null">Bağla</button><button class="btn-primary" form="term-form">Saxla</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
