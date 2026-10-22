<x-layouts.app title="Hazır yemək">
<div x-data="{ act: null, add: false, recipes: @js($items->mapWithKeys(fn ($i) => [$i->id => $i->recipeItems->map(fn ($r) => ['title' => $r->inventoryItem->title, 'per' => (float) $r->quantity, 'have' => (float) $r->inventoryItem->quantity, 'price' => $r->inventoryItem->cost_price !== null ? (float) $r->inventoryItem->cost_price : null, 'unit' => $units[$r->inventoryItem->unit] ?? $r->inventoryItem->unit])])) }">
    <x-page-header title="Hazır yemək stoku" subtitle="Porsiya ilə hazırlanıb satılan yeməklər. Hazırlananda xammal anbardan silinir, satılanda porsiya hazır stokdan düşür.">
        <a href="{{ route('staff-meals.index') }}" class="btn-secondary"><x-icon name="utensils" class="size-4" /> Personal yeməyi</a>
        <button class="btn-primary" @click="add = true"><x-icon name="plus" class="size-4" /> Yemək əlavə et</button>
    </x-page-header>

    @php($counts = $items->countBy(fn ($i) => $i->readyStockStatus()))
    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['Hazır stokla izlənir', $items->count(), 'border-l-zinc-400'], ['Kifayət qədər', $counts['in'] ?? 0, 'border-l-brand-500'], ['Az qalıb', $counts['low'] ?? 0, 'border-l-amber-500'], ['Bitib', $counts['out'] ?? 0, 'border-l-red-500']] as [$label, $value, $border])
            <div class="card card-pad border-l-4 {{ $border }}"><p class="text-sm font-semibold text-zinc-500">{{ $label }}</p><p class="text-2xl font-black">{{ $value }}</p></div>
        @endforeach
    </div>

    <div class="card mb-6 overflow-x-auto">
        <table class="table-base">
            <thead><tr>
                <th>Yemək</th><th class="text-right">Hazır qalıq</th><th class="text-right">Minimum</th>
                <th class="text-right" title="Bu gün">Hazırlanıb</th><th class="text-right">Satılıb</th><th class="text-right">Personal</th><th class="text-right">İsraf</th>
                <th class="text-right">Maya / porsiya</th><th>Resept (1 porsiya)</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($items as $item)
                @php($status = $item->readyStockStatus())
                @php($t = $today[$item->id] ?? collect())
                <tr>
                    <td><p class="font-bold">{{ $item->title }}</p><p class="text-xs text-zinc-500">{{ $item->category?->title }}</p></td>
                    <td class="text-right">
                        <span @class(['text-lg font-black tabular-nums', 'text-red-600' => $status === 'out', 'text-amber-600' => $status === 'low'])>{{ qty($item->stock_quantity) }}</span>
                        <span class="text-xs text-zinc-500">porsiya</span>
                        @if ($status === 'out')<span class="badge-red ml-1">Bitib</span>@elseif ($status === 'low')<span class="badge-amber ml-1">Az</span>@endif
                    </td>
                    <td class="text-right text-zinc-500">{{ qty($item->stock_min) }}</td>
                    <td class="text-right tabular-nums text-brand-700">{{ ($v = $t['production'] ?? 0) ? '+'.qty($v) : '—' }}</td>
                    <td class="text-right tabular-nums">{{ qty(max(0, ($t['sale'] ?? 0))) ?: '—' }}</td>
                    <td class="text-right tabular-nums">{{ qty($t['staff'] ?? 0) ?: '—' }}</td>
                    <td class="text-right tabular-nums text-red-600">{{ qty($t['wastage'] ?? 0) ?: '—' }}</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($item->stock_cost !== null)
                            <span class="font-semibold">{{ money($item->stock_cost) }}</span>
                            @if ($item->price > 0)<p @class(['text-xs', 'text-red-600' => ($item->price - $item->stock_cost) / $item->price < 0.3, 'text-zinc-500' => ($item->price - $item->stock_cost) / $item->price >= 0.3])>marja {{ round(($item->price - $item->stock_cost) / $item->price * 100) }}%</p>@endif
                        @else
                            <span class="text-xs text-zinc-400" title="Reseptdəki bütün xammalın alış qiyməti yazılandan sonra növbəti hazırlamada hesablanır">bilinmir</span>
                        @endif
                    </td>
                    <td class="max-w-56 text-xs text-zinc-500">
                        @forelse ($item->recipeItems as $r)
                            {{ $r->inventoryItem->title }} {{ qty($r->quantity) }} {{ $units[$r->inventoryItem->unit] ?? '' }}@if (! $loop->last), @endif
                        @empty
                            <span class="text-amber-600">Resept yoxdur — anbardan silinməyəcək</span>
                        @endforelse
                    </td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn-primary btn-sm" @click="act = { kind: 'produce', id: {{ $item->id }}, title: @js($item->title), portions: '', note: '' }"><x-icon name="flame" class="size-4" /> Hazırla</button>
                        <button class="btn-secondary btn-sm" @click="act = { kind: 'count', id: {{ $item->id }}, title: @js($item->title), actual: @js(qty($item->stock_quantity)), note: '' }">Sayım</button>
                        <button class="btn-ghost btn-sm text-red-600" @click="act = { kind: 'waste', id: {{ $item->id }}, title: @js($item->title), max: {{ (float) $item->stock_quantity }}, portions: '', note: '' }">İsraf</button>
                        <button class="btn-ghost btn-sm" @click="act = { kind: 'edit', id: {{ $item->id }}, title: @js($item->title), stock_min: @js(qty($item->stock_min)) }"><x-icon name="edit" class="size-4" /></button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10"><x-empty icon="chef" title="Hazır stokla izlənən yemək yoxdur">Plov, dolma, sup kimi əvvəlcədən porsiya ilə bişirilən yeməkləri əlavə edin. Satışda porsiya sayı avtomatik azalacaq.</x-empty></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
        <h2 class="text-lg font-extrabold">Hazır stok hərəkətləri</h2>
        <form class="flex gap-2">
            <select name="item" class="input"><option value="">Bütün yeməklər</option>@foreach ($items as $i)<option value="{{ $i->id }}" @selected(request('item') == $i->id)>{{ $i->title }}</option>@endforeach</select>
            <select name="type" class="input"><option value="">Bütün növlər</option>@foreach (\App\Models\ReadyStockMovement::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select>
            <button class="btn-secondary"><x-icon name="search" class="size-4" /></button>
        </form>
    </div>
    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Tarix</th><th>Yemək</th><th>Növ</th><th class="text-right">Porsiya</th><th class="text-right">Qalıq</th><th>Qeyd</th><th>İşçi</th></tr></thead>
            <tbody>
            @forelse ($movements as $m)
                <tr>
                    <td class="whitespace-nowrap text-zinc-500">{{ $m->created_at->format('d.m.Y H:i') }}</td>
                    <td class="font-semibold">{{ $m->menuItem?->title }}</td>
                    <td>{{ \App\Models\ReadyStockMovement::TYPES[$m->type] ?? $m->type }}</td>
                    <td @class(['text-right font-bold tabular-nums', 'text-brand-700' => $m->quantity > 0, 'text-red-600' => $m->quantity < 0])>{{ $m->quantity > 0 ? '+' : '' }}{{ qty($m->quantity) }}</td>
                    <td class="text-right tabular-nums text-zinc-500">{{ qty($m->balance) }}</td>
                    <td class="text-zinc-500">{{ $m->note }}</td>
                    <td class="text-zinc-500">{{ $m->user?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty icon="history" title="Hərəkət yoxdur" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $movements->links() }}</div>

    {{-- Hazırla / sayım / israf / minimum --}}
    <x-modal show="act" close="act = null" title="Hazır stok" width="max-w-md">
        <template x-if="act"><form method="POST" id="act-form" class="space-y-3"
              :action="@js(url('dashboard/ready-stock')) + '/' + act.id + ({ produce: '/produce', waste: '/waste', count: '/count', edit: '' })[act.kind]">
            @csrf
            <template x-if="act.kind === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
            <p class="font-extrabold" x-text="act.title"></p>

            <template x-if="act.kind === 'produce'"><div class="space-y-3">
                <div><label class="label">Neçə porsiya hazırlandı?</label><input type="number" step="1" min="1" name="portions" x-model="act.portions" class="input text-lg" required autofocus></div>
                <div class="rounded-xl bg-zinc-50 p-3 text-sm" x-show="(recipes[act.id] || []).length">
                    <p class="mb-1 text-xs font-bold text-zinc-500 uppercase">Anbardan silinəcək</p>
                    <template x-for="r in recipes[act.id]" :key="r.title">
                        <div class="flex justify-between py-0.5" :class="r.per * (act.portions || 0) > r.have && 'text-red-600 font-bold'">
                            <span x-text="r.title"></span>
                            <span><span x-text="+(r.per * (act.portions || 0)).toFixed(3)"></span> <span x-text="r.unit"></span> <span class="text-xs text-zinc-400">/ var: <span x-text="+r.have.toFixed(3)"></span></span></span>
                        </div>
                    </template>
                    <p class="mt-2 border-t border-zinc-200 pt-2 font-semibold" x-show="act.portions > 0">
                        <template x-if="recipes[act.id].every(r => r.price !== null)"><span>Partiyanın mayası: <span x-text="money(recipes[act.id].reduce((s, r) => s + r.per * r.price, 0) * act.portions)"></span> · porsiya <span x-text="money(recipes[act.id].reduce((s, r) => s + r.per * r.price, 0))"></span></span></template>
                        <template x-if="!recipes[act.id].every(r => r.price !== null)"><span class="text-amber-700">Bəzi xammalın alış qiyməti yoxdur — maya hesablanmayacaq</span></template>
                    </p>
                </div>
                <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800" x-show="!(recipes[act.id] || []).length">Bu yeməyin resepti yoxdur — anbardan xammal silinməyəcək. Resepti Ayarlar → Menyu bölməsində əlavə edin.</p>
                <div><label class="label">Qeyd</label><input name="note" x-model="act.note" class="input" placeholder="Məs. səhər partiyası"></div>
            </div></template>

            <template x-if="act.kind === 'waste'"><div class="space-y-3">
                <div><label class="label">İsraf olunan porsiya (maks. <span x-text="act.max"></span>)</label><input type="number" step="1" min="1" :max="act.max" name="portions" x-model="act.portions" class="input" required></div>
                <div><label class="label">Səbəb *</label><input name="note" x-model="act.note" class="input" placeholder="Məs. vaxtı keçdi, yerə düşdü" required></div>
            </div></template>

            <template x-if="act.kind === 'count'"><div class="space-y-3">
                <div><label class="label">Faktiki qalıq (porsiya)</label><input type="number" step="1" min="0" name="actual" x-model="act.actual" class="input text-lg" required></div>
                <div><label class="label">Qeyd</label><input name="note" x-model="act.note" class="input" placeholder="Məs. gün sonu sayımı"></div>
                <p class="text-xs text-zinc-500">Fərq "Sayım" hərəkəti kimi qeydə alınır. Anbar xammalına toxunulmur.</p>
            </div></template>

            <template x-if="act.kind === 'edit'"><div class="space-y-3">
                <div><label class="label">Minimum hədd (porsiya)</label><input type="number" step="1" min="0" name="stock_min" x-model="act.stock_min" class="input" required></div>
                <p class="text-xs text-zinc-500">Qalıq bu həddə düşəndə "Az qalıb" kimi göstərilir.</p>
            </div></template>
        </form></template>
        <x-slot:footer>
            <template x-if="act?.kind === 'edit'">
                <form method="POST" :action="@js(url('dashboard/ready-stock')) + '/' + act.id" class="mr-auto" onsubmit="return confirm('İzləmə dayandırılsın? Qalıq sıfırlanacaq, yemək yenidən satışda reseptlə anbardan silinəcək.')">@csrf @method('DELETE')<button class="btn-ghost text-red-600">İzləməni dayandır</button></form>
            </template>
            <button class="btn-secondary" @click="act = null">Bağla</button>
            <button class="btn-primary" form="act-form">Saxla</button>
        </x-slot:footer>
    </x-modal>

    <x-modal show="add" close="add = false" title="Hazır stoka yemək əlavə et" width="max-w-md">
        <form method="POST" action="{{ route('ready-stock.track') }}" id="track-form" class="space-y-3">
            @csrf
            <div><label class="label">Menyu məhsulu *</label><select name="menu_item_id" class="input" required><option value="">Seçin</option>@foreach ($untracked as $m)<option value="{{ $m->id }}">{{ $m->title }}</option>@endforeach</select></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Hazırda olan porsiya</label><input type="number" step="1" min="0" name="portions" class="input" placeholder="0"></div>
                <div><label class="label">Minimum hədd</label><input type="number" step="1" min="0" name="stock_min" class="input" placeholder="0"></div>
            </div>
            <p class="text-xs text-zinc-500">Bundan sonra bu yemək POS-da yalnız hazır porsiya qədər satıla bilər. Yeni porsiyaları "Hazırla" ilə əlavə edin — xammal reseptə görə anbardan silinəcək.</p>
        </form>
        <x-slot:footer><button class="btn-secondary" @click="add = false">Bağla</button><button class="btn-primary" form="track-form">Əlavə et</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
