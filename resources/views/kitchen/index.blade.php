<x-layouts.app title="Mətbəx">
<div x-data="{
    ...poller(@js(route('kitchen.feed')), 5000),
    ...kitchenStation(@js($departments), @js(route('orders.token', '__ID__'))),
    realtime(e) { if (e.type === 'created') { if (@js($soundNewOrder)) playSound('alert'); toast('Yeni sifariş #' + e.token_no) } },
}" x-effect="autoPrint(rows)">
    <x-page-header title="Mətbəx" subtitle="Hazırlanacaq sifarişlər. Yeni sifariş gələndə səs siqnalı verilir.">
        <x-live-badge />
        <button class="btn-secondary" @click="load()"><x-icon name="refresh" class="size-4" /> Yenilə</button>
    </x-page-header>

    {{-- Şöbə seçimi (bu cihaz üçün yadda qalır) və çap stansiyası --}}
    <div class="mb-4 flex flex-wrap items-center gap-2" x-show="departments.length > 1">
        <button class="chip" :class="!department && 'active'" @click="setDepartment(null)">Hamısı</button>
        <template x-for="d in departments" :key="d.id">
            <button class="chip" :class="department === d.id && 'active'" @click="setDepartment(d.id)" x-text="d.name"></button>
        </template>
        <label x-show="department" class="ml-auto flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold" :class="station ? 'border-brand-400 bg-brand-50 text-brand-800' : 'border-zinc-200 text-zinc-600'">
            <input type="checkbox" class="rounded text-brand-500" :checked="station" @change="setStation($event.target.checked)">
            <span>Bu cihaz <b x-text="currentDepartment()?.name"></b> çeklərini çap edir</span>
        </label>
    </div>
    <p x-show="station && currentDepartment()?.print_at !== 'station'" x-cloak class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
        Diqqət: Ayarlar → Şöbələr-də <b x-text="currentDepartment()?.name"></b> şöbəsinin çap yeri "Kassada"dır — çek həm kassada, həm burada çıxacaq. "Şöbənin öz cihazında" seçin.
    </p>

    <div x-show="!loading && !visible().length" class="card"><x-empty icon="chef" title="Növbədə sifariş yoxdur">Yeni sifariş gələndə burada görünəcək.</x-empty></div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        <template x-for="o in visible()" :key="o.id">
            <div class="card flex flex-col overflow-hidden" :class="o.minutes >= 20 ? 'ring-2 ring-red-400' : (o.minutes >= 10 ? 'ring-2 ring-amber-300' : '')">
                <div class="flex items-center justify-between bg-zinc-50 px-4 py-3">
                    <div>
                        <p class="font-extrabold"><span x-text="o.title"></span> <span x-show="o.is_addition" class="badge-amber align-middle">ƏLAVƏ</span></p>
                        <p x-show="o.transferred_from" class="text-xs font-bold text-sky-700" x-text="o.transferred_from + '-dən köçürülüb'"></p>
                        <p class="text-xs text-zinc-500" x-text="o.delivery_type + (o.waiter ? ' · ' + o.waiter : '')"></p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-black" x-text="'#' + o.token_no"></p>
                        <p class="flex items-center justify-end gap-1 text-xs font-semibold" :class="o.minutes >= 20 ? 'text-red-600' : 'text-zinc-500'"><x-icon name="clock" class="size-3.5" /><span x-text="o.minutes + ' dəq'"></span></p>
                    </div>
                </div>
                <ul class="flex-1 divide-y divide-zinc-100">
                    <template x-for="i in o.items" :key="i.id">
                        <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                            <div class="min-w-0">
                                <p class="font-bold" :class="i.status === 'ready' && 'text-zinc-400 line-through'" x-text="i.qty + ' × ' + i.title + (i.variant ? ' (' + i.variant + ')' : '')"></p>
                                <p class="text-xs text-zinc-500" x-show="i.addons" x-text="'+ ' + i.addons"></p>
                                <p class="text-xs font-semibold text-amber-700" x-show="i.notes" x-text="i.notes"></p>
                            </div>
                            <button x-show="i.status === 'created'" class="btn-secondary btn-sm shrink-0" @click="post(@js(route('kitchen.item', '__ID__')).replace('__ID__', i.id), { status: 'preparing' })">Başla</button>
                            <button x-show="i.status === 'preparing'" class="btn-sm btn shrink-0 bg-amber-500 text-white hover:bg-amber-600" @click="post(@js(route('kitchen.item', '__ID__')).replace('__ID__', i.id), { status: 'ready' })">Hazırdır</button>
                            <span x-show="i.status === 'ready'" class="badge-green shrink-0">Hazır</span>
                        </li>
                    </template>
                </ul>
                <p class="px-4 pb-2 text-xs font-semibold text-amber-700" x-show="o.notes" x-text="'Qeyd: ' + o.notes"></p>
                <div class="border-t border-zinc-100 p-3">
                    <button class="btn-primary w-full" @click="post(@js(route('kitchen.ready', '__ID__')).replace('__ID__', o.id), { department_id: department })"><x-icon name="check" class="size-4" /> Hamısı hazırdır</button>
                </div>
            </div>
        </template>
    </div>
</div>
</x-layouts.app>
