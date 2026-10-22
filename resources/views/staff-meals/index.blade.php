<x-layouts.app title="Personal yeməyi">
<div x-data="staffMeal(@js($menu), @js((string) old('user_id', '')))">
    <x-page-header title="Personal yeməyi" subtitle="İşçilərə verilən yeməklər. Hazır stokdan və ya reseptə görə anbardan silinir, dəyəri uçota düşür.">
        <a href="{{ route('ready-stock.index') }}" class="btn-secondary"><x-icon name="chef" class="size-4" /> Hazır yemək</a>
    </x-page-header>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_26rem]">
        {{-- Yeni qeyd --}}
        <form method="POST" action="{{ route('staff-meals.store') }}" class="card card-pad space-y-4" @submit="if (!cart.length) { $event.preventDefault(); toast('Ən azı bir yemək seçin', 'error') }">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">İşçi *</label>
                    <select name="user_id" x-model="userId" class="input">
                        <option value="">— Siyahıda yoxdur (adını yazın) —</option>
                        @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(old('user_id') == $e->id)>{{ $e->name }} · {{ $e->roleLabel() }}</option>@endforeach
                    </select>
                </div>
                <div x-show="!userId"><label class="label">İşçinin adı *</label><input name="employee_name" value="{{ old('employee_name') }}" class="input" placeholder="Məs. Qabyuyan Səbinə" :required="!userId"></div>
            </div>
            <div>
                <label class="label">Öğün</label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach (\App\Models\StaffMeal::MEAL_TYPES as $k => $v)
                        <label class="cursor-pointer rounded-xl border border-zinc-200 py-2 text-center text-sm font-bold has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50"><input type="radio" name="meal_type" value="{{ $k }}" class="sr-only" @checked(old('meal_type', now()->hour < 11 ? 'breakfast' : (now()->hour < 17 ? 'lunch' : 'dinner')) === $k)>{{ $v }}</label>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="mb-2 flex flex-wrap gap-2">
                    <input x-model="search" class="input max-w-60" placeholder="Yemək axtar">
                    <button type="button" class="chip" :class="!category && 'active'" @click="category = null">Hamısı</button>
                    @foreach ($categories as $c)<button type="button" class="chip" :class="category === {{ $c->id }} && 'active'" @click="category = {{ $c->id }}">{{ $c->title }}</button>@endforeach
                </div>
                <div class="grid max-h-80 grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3 2xl:grid-cols-4">
                    <template x-for="m in filtered" :key="m.id">
                        <button type="button" @click="add(m)" :disabled="m.stock !== null && left(m) <= 0"
                                class="flex flex-col rounded-xl border border-zinc-200 p-2.5 text-left text-sm transition hover:border-brand-300 disabled:cursor-not-allowed disabled:opacity-50">
                            <span class="font-bold" x-text="m.title"></span>
                            <span class="mt-1 flex items-center justify-between gap-1 text-xs">
                                <span class="text-zinc-500" x-text="money(m.price)"></span>
                                <template x-if="m.stock !== null"><span :class="left(m) > 0 ? 'badge-green' : 'badge-red'" x-text="left(m) > 0 ? left(m) + ' porsiya' : 'Bitib'"></span></template>
                            </span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200">
                <template x-for="(line, i) in cart" :key="line.id">
                    <div class="flex items-center gap-2 border-b border-zinc-100 px-3 py-2 last:border-0">
                        <input type="hidden" :name="`items[${i}][menu_item_id]`" :value="line.id">
                        <input type="hidden" :name="`items[${i}][quantity]`" :value="line.quantity">
                        <span class="flex-1 font-semibold" x-text="line.title"></span>
                        <button type="button" class="btn-ghost btn-sm" @click="dec(line)"><x-icon name="minus" class="size-4" /></button>
                        <span class="w-6 text-center font-bold" x-text="line.quantity"></span>
                        <button type="button" class="btn-ghost btn-sm" @click="add(line.item)" :disabled="line.item.stock !== null && left(line.item) <= 0"><x-icon name="plus" class="size-4" /></button>
                        <span class="w-20 text-right text-zinc-500" x-text="money(line.item.price * line.quantity)"></span>
                    </div>
                </template>
                <p x-show="!cart.length" class="px-3 py-4 text-center text-sm text-zinc-500">Yuxarıdan yemək seçin.</p>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-48 flex-1"><label class="label">Qeyd</label><input name="note" value="{{ old('note') }}" class="input" placeholder="Məs. gecə növbəsi"></div>
                <div class="text-right"><p class="text-xs text-zinc-500">Dəyəri (menyu qiyməti)</p><p class="text-xl font-black" x-text="money(total)"></p></div>
                <button class="btn-primary h-11"><x-icon name="check" class="size-4" /> Qeydə al</button>
            </div>
        </form>

        {{-- Siyahı --}}
        <div class="space-y-4">
            <form class="card flex flex-wrap items-end gap-2 p-3">
                <div><label class="label">Başlanğıc</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="input"></div>
                <div><label class="label">Son</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="input"></div>
                <button class="btn-secondary h-10"><x-icon name="search" class="size-4" /></button>
            </form>

            <div class="grid grid-cols-2 gap-2">
                @foreach ([['Qeyd', $stats->meals, null], ['Porsiya', $stats->portions, null], ['Menyu qiyməti ilə', money($stats->value), null], ['Maya dəyəri', money($stats->cost), $stats->costUnknown ? $stats->costUnknown.' qeydin mayası bilinmir' : null]] as [$label, $value, $hint])
                    <div class="card card-pad"><p class="text-xs font-semibold text-zinc-500">{{ $label }}</p><p class="text-xl font-black">{{ $value }}</p>@if ($hint)<p class="text-[11px] text-amber-600">{{ $hint }}</p>@endif</div>
                @endforeach
            </div>

            @if ($byEmployee->count() > 1)
                <div class="card card-pad">
                    <h3 class="mb-2 font-extrabold">İşçilər üzrə</h3>
                    @foreach ($byEmployee as $row)
                        <div class="flex justify-between py-1 text-sm"><span class="font-semibold">{{ $row->name }}</span><span class="text-zinc-500">{{ $row->meals }} dəfə · maya <b class="text-ink">{{ money($row->cost) }}</b></span></div>
                    @endforeach
                </div>
            @endif

            <div class="card divide-y divide-zinc-100">
                @forelse ($meals as $meal)
                    <div @class(['p-3 text-sm', 'opacity-50' => $meal->status === 'cancelled'])>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-bold">{{ $meal->employee_name }} <span class="font-normal text-zinc-500">· {{ \App\Models\StaffMeal::MEAL_TYPES[$meal->meal_type] ?? $meal->meal_type }}</span></p>
                                <p class="text-zinc-600">{{ $meal->items->map(fn ($i) => $i->quantity.' × '.$i->title)->implode(', ') }}</p>
                                <p class="text-xs text-zinc-400">{{ $meal->created_at->format('d.m H:i') }}@if ($meal->creator) · verdi: {{ $meal->creator->name }}@endif @if ($meal->note) · {{ $meal->note }}@endif</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="font-bold">{{ money($meal->total) }}</p>
                                <p class="text-[11px] text-zinc-500">maya: {{ $meal->cost !== null ? money($meal->cost) : 'bilinmir' }}</p>
                                @if ($meal->status === 'cancelled')
                                    <span class="badge-red">Ləğv</span>
                                @else
                                    <form method="POST" action="{{ route('staff-meals.cancel', $meal) }}" onsubmit="return confirm('Ləğv edilsin? Porsiyalar stoka qaytarılacaq.')">@csrf<button class="text-xs font-semibold text-red-600 hover:underline">Ləğv et</button></form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <x-empty icon="utensils" title="Bu dövrdə personal yeməyi yoxdur" />
                @endforelse
            </div>
        </div>
    </div>
</div>

</x-layouts.app>
