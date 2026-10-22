<x-layouts.app title="POS" compact>
<div x-data="pos(@js($posData))" @keydown.window.f9.prevent="toggleFull()" class="pos-screen flex flex-col gap-3 lg:flex-row">

    {{-- Sol: menyu --}}
    <section class="card flex min-h-0 flex-1 flex-col">
        <div class="flex flex-wrap items-center gap-2 border-b border-zinc-100 p-3">
            <div class="flex min-w-0 flex-1 gap-2 overflow-x-auto pb-1">
                <button class="chip" :class="!category && 'active'" @click="category = null">Hamısı</button>
                <template x-for="c in categories" :key="c.id">
                    <button class="chip" :class="category === c.id && 'active'" @click="category = c.id" x-text="c.title"></button>
                </template>
            </div>
            <div class="relative w-full sm:w-56">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />
                <input x-model="search" type="search" placeholder="Axtar..." class="input pl-9">
            </div>
        </div>
        <div class="flex flex-wrap gap-2 border-b border-zinc-100 px-3 py-2">
            <button class="btn-secondary btn-sm" @click="clear()"><x-icon name="plus" class="size-4" /> Yeni</button>
            <button class="btn-secondary btn-sm" @click="openDrafts()"><x-icon name="drafts" class="size-4" /> Qaralamalar</button>
            <button class="btn-secondary btn-sm relative" @click="modal = 'qr'; refreshQrCount()">
                <x-icon name="qr" class="size-4" /> QR sifarişlər
                <span x-show="qrOrders.length" x-text="qrOrders.length" class="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full bg-red-500 text-[10px] text-white"></span>
            </button>
            <button class="btn-secondary btn-sm" @click="openTables()"><x-icon name="table" class="size-4" /> Masalar</button>
            <a href="{{ route('orders.index') }}" class="btn-secondary btn-sm"><x-icon name="orders" class="size-4" /> Sifarişlər</a>
            <button class="btn-secondary btn-sm" @click="modal = 'display'" :class="display && 'border-brand-400 text-brand-700'">
                <x-icon name="online" class="size-4" /> <span x-text="display ? display.name : 'Müştəri ekranı'"></span>
            </button>
            <div class="ml-auto flex flex-wrap items-center gap-1.5">
                <x-live-badge />
                <template x-if="$store.terminal.active && $store.terminal.operator">
                    <div class="flex items-center gap-1.5">
                        <span class="badge-green py-1.5 text-sm"><x-icon name="users" class="size-4" /> <span x-text="$store.terminal.operator.name + ' · ' + $store.terminal.operator.role"></span></span>
                        <span class="text-xs text-zinc-400 tabular-nums" x-text="$store.terminal.idleLeft + ' san'" title="Hərəkətsiz qalsa kilidlənəcək"></span>
                        <button class="btn-secondary btn-sm" @click="$store.terminal.lock()"><x-icon name="logout" class="size-4" /> Kilidlə</button>
                    </div>
                </template>
                @if (auth()->user()->isAdmin() || auth()->user()->hasScope('SETTINGS'))
                    <button x-show="!$store.terminal.active" class="btn-secondary btn-sm" @click="$store.terminal.enter()" title="Cihazı PIN ilə işləyən POS terminalına çevir">
                        <x-icon name="qr" class="size-4" /> Terminal rejimi
                    </button>
                @endif
                <button class="btn-secondary btn-sm" @click="toggleFull()" :title="full ? 'Tam ekrandan çıx (F9)' : 'Tam ekran (F9)'">
                    <x-icon name="maximize" class="size-4" x-show="!full" />
                    <x-icon name="minimize" class="size-4" x-show="full" x-cloak />
                    <span x-text="full ? 'Çıx' : 'Tam ekran'"></span>
                </button>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-3">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                <template x-for="item in filtered" :key="item.id">
                    <button @click="open(item)" class="group relative flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white text-left transition hover:border-brand-300 hover:shadow-sm">
                        <div class="relative aspect-[4/3] bg-gradient-to-br from-brand-50 to-zinc-100">
                            <template x-if="item.image"><img :src="item.image" :alt="item.title" class="absolute inset-0 size-full object-cover" loading="lazy"></template>
                            <template x-if="!item.image"><span class="absolute inset-0 flex items-center justify-center text-3xl font-black text-brand-300" x-text="item.title.slice(0, 1)"></span></template>
                            <span x-show="item.category" x-text="item.category" class="absolute top-2 left-2 rounded-md bg-brand-500/90 px-2 py-0.5 text-[11px] font-bold text-white"></span>
                            <span class="absolute right-2 bottom-2 flex size-8 items-center justify-center rounded-full bg-brand-500 text-white shadow transition group-hover:scale-110"><x-icon name="plus" class="size-4" /></span>
                        </div>
                        <div class="flex flex-1 flex-col p-3">
                            <p class="line-clamp-2 text-sm font-bold" x-text="item.title"></p>
                            <p class="text-[11px] text-zinc-500" x-show="item.variants.length || item.addons.length"
                               x-text="[item.variants.length && item.variants.length + ' variant', item.addons.length && item.addons.length + ' əlavə'].filter(Boolean).join(' · ')"></p>
                            <p class="mt-auto pt-2 font-extrabold text-brand-600" x-text="money(item.price)"></p>
                        </div>
                    </button>
                </template>
            </div>
            <p x-show="!filtered.length" class="py-16 text-center text-zinc-500">Məhsul tapılmadı.</p>
        </div>
    </section>

    {{-- Sağ: səbət --}}
    <aside class="card flex min-h-0 w-full flex-col lg:w-[400px]">
        <div class="space-y-2 border-b border-zinc-100 p-3">
            <div class="relative" x-show="!customer">
                <input x-model.debounce.300ms="customerQuery" class="input pr-20" placeholder="Müştəri: ad və ya telefon (boş = gəlmə müştəri)">
                <button class="btn-ghost btn-sm absolute top-1/2 right-1 -translate-y-1/2" @click="modal = 'customer'"><x-icon name="plus" class="size-4" /> Yeni</button>
                <div x-show="customerResults.length" class="absolute z-10 mt-1 w-full overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg">
                    <template x-for="c in customerResults" :key="c.id">
                        <button class="block w-full px-3 py-2 text-left text-sm hover:bg-brand-50" @click="pickCustomer(c)"><span class="font-semibold" x-text="c.name"></span> <span class="text-zinc-500" x-text="c.phone"></span></button>
                    </template>
                </div>
            </div>
            <div x-show="customer" class="flex items-center justify-between rounded-xl bg-brand-50 px-3 py-2 text-sm">
                <span><span class="font-bold" x-text="customer?.name"></span> <span class="text-zinc-500" x-text="customer?.phone"></span></span>
                <button class="btn-ghost btn-sm" @click="customer = null"><x-icon name="x" class="size-4" /></button>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <select x-model="deliveryType" class="input">
                    <template x-for="(label, key) in deliveryTypes" :key="key"><option :value="key" x-text="label" :selected="key === deliveryType"></option></template>
                </select>
                <select x-model="tableId" class="input" :disabled="deliveryType !== 'dinein'">
                    <option value="">Masa seçin</option>
                    <template x-for="t in tables" :key="t.id"><option :value="t.id" x-text="t.label + ' (' + t.seats + ' nəfər)' + (t.deposit > 0 ? ' • depozit ' + money(t.deposit) : '') + (t.busy ? ' • dolu' : '')" :selected="String(t.id) === String(tableId)"></option></template>
                </select>
            </div>
        </div>

        <div x-show="tableOpenOrders" x-cloak class="border-b border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
            <p class="font-bold" x-text="'Bu masada açıq hesab var: ' + (tableOpenOrders?.orders.length ?? 0) + ' sifariş, ' + money(tableOpenOrders?.due)"></p>
            <p>Yeni sifariş <b>əlavə</b> kimi gedəcək: mətbəxə ayrıca çek çıxır, hesab birlikdə bağlanır. Hesabı bağlamaq üçün: <button type="button" class="font-bold underline" @click="openTables()">Masalar</button> → masa → <b>Hesabı bağla</b>.</p>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto">
            <template x-for="line in cart" :key="line.key">
                <div class="flex gap-3 border-b border-zinc-100 px-3 py-2.5">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold" x-text="line.title"></p>
                        <p class="text-xs text-zinc-500" x-show="line.subtitle" x-text="line.subtitle"></p>
                        <button class="mt-0.5 text-xs text-brand-600 hover:underline" @click="editNote(line)" x-text="line.notes ? '📝 ' + line.notes : '+ qeyd'"></button>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <p class="text-sm font-extrabold" x-text="money(line.price * line.quantity)"></p>
                        <div class="flex items-center rounded-lg border border-zinc-200">
                            <button class="px-2 py-1 text-zinc-500 hover:text-red-600" @click="dec(line)"><x-icon name="minus" class="size-3.5" /></button>
                            <span class="w-7 text-center text-sm font-bold" x-text="line.quantity"></span>
                            <button class="px-2 py-1 text-zinc-500 hover:text-brand-600" @click="inc(line)"><x-icon name="plus" class="size-3.5" /></button>
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="!cart.length" class="flex h-full flex-col items-center justify-center p-8 text-center text-sm text-zinc-400">
                <x-icon name="pos" class="mb-2 size-10" /> Səbət boşdur. Menyudan məhsul seçin.
            </div>
        </div>

        <div class="border-t border-zinc-100 p-3" x-show="cart.length">
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-zinc-500">Ara cəm (<span x-text="count"></span>)</dt><dd x-text="money(totals.sub)"></dd></div>
                <div class="flex justify-between">
                    <dt><button class="text-brand-600 hover:underline" @click="modal = 'discount'">Endirim <span x-show="discountValue > 0" x-text="discountType === 'percentage' ? '(' + discountValue + '%)' : ''"></span></button></dt>
                    <dd class="text-red-600" x-text="'−' + money(totals.discount)"></dd>
                </div>
                <div class="flex justify-between" x-show="taxEnabled"><dt class="text-zinc-500">Vergi</dt><dd x-text="money(totals.tax)"></dd></div>
                <div class="flex items-center justify-between" x-show="serviceApplies">
                    <dt>
                        <label class="flex cursor-pointer items-center gap-2 text-zinc-500" title="Bu sifariş üçün servis haqqını aç/söndür">
                            <button type="button" role="switch" :aria-checked="!noServiceCharge" @click="noServiceCharge = !noServiceCharge"
                                    class="relative h-5 w-9 rounded-full transition" :class="noServiceCharge ? 'bg-zinc-300' : 'bg-brand-500'">
                                <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all" :class="noServiceCharge ? 'left-0.5' : 'left-[18px]'"></span>
                            </button>
                            <span x-text="'Servis haqqı (' + service.rate + '%)'"></span>
                        </label>
                    </dt>
                    <dd :class="noServiceCharge && 'text-zinc-400 line-through'" x-text="money(noServiceCharge ? (totals.sub - totals.discount) * service.rate / 100 : totals.service)"></dd>
                </div>
                <div class="flex justify-between text-amber-700" x-show="totals.deposit > 0"><dt x-text="totals.topup > 0 ? 'Depozitə qədər fərq (min. ' + money(totals.deposit) + ')' : 'Kabinet depoziti ' + money(totals.deposit) + ' — qarşılanıb'"></dt><dd x-show="totals.topup > 0" x-text="money(totals.topup)"></dd></div>
                <div class="flex justify-between border-t border-dashed border-zinc-200 pt-1.5 text-base font-black"><dt>Ödəniləcək</dt><dd x-text="money(totals.total)"></dd></div>
            </dl>
            <input x-model="notes" class="input mt-2 py-2" placeholder="Sifariş qeydi (istəyə bağlı)" maxlength="255">
        </div>

        <div class="grid grid-cols-[auto_1fr] gap-2 border-t border-zinc-100 p-3">
            <button class="btn-secondary" :disabled="!cart.length || busy" @click="modal = 'draft'"><x-icon name="drafts" class="size-4" /> Qaralama</button>
            <button :class="$store.terminal.canPay ? 'btn-secondary' : 'btn-primary py-3 text-base'" :disabled="!cart.length || busy" @click="submit('kitchen')"><x-icon name="chef" class="size-4" /> Mətbəxə göndər</button>
            <button x-show="$store.terminal.canPay" class="btn-primary col-span-2 py-3 text-base" :disabled="!cart.length || busy" @click="modal = 'pay'">
                <x-icon name="cash" class="size-5" /> Ödəniş al · <span x-text="money(totals.total)"></span>
            </button>
        </div>
    </aside>

    {{-- Variant / əlavə seçimi --}}
    <x-modal show="picker" close="picker = null" title="<span x-text='picker?.item.title'></span>">
        <template x-if="picker">
            <div class="space-y-5">
                <div x-show="picker.item.variants.length">
                    <p class="label">Variant</p>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="v in picker.item.variants" :key="v.id">
                            <button class="rounded-xl border px-3 py-2.5 text-left text-sm transition" :class="picker.variantId === v.id ? 'border-brand-500 bg-brand-50' : 'border-zinc-200'" @click="picker.variantId = v.id">
                                <span class="block font-bold" x-text="v.title"></span><span class="text-zinc-500" x-text="money(v.price)"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <div x-show="picker.item.addons.length">
                    <p class="label">Əlavələr</p>
                    <div class="space-y-1.5">
                        <template x-for="a in picker.item.addons" :key="a.id">
                            <label class="flex cursor-pointer items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                <span class="flex items-center gap-2"><input type="checkbox" :value="a.id" x-model.number="picker.addonIds" class="rounded text-brand-500"> <span x-text="a.title"></span></span>
                                <span class="text-zinc-500" x-text="'+' + money(a.price)"></span>
                            </label>
                        </template>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center rounded-xl border border-zinc-200">
                        <button class="px-3 py-2" @click="picker.quantity > 1 && picker.quantity--"><x-icon name="minus" class="size-4" /></button>
                        <span class="w-8 text-center font-bold" x-text="picker.quantity"></span>
                        <button class="px-3 py-2" @click="picker.quantity++"><x-icon name="plus" class="size-4" /></button>
                    </div>
                    <input x-model="picker.notes" class="input" placeholder="Qeyd (məs. acısız)" maxlength="100">
                </div>
                <button class="btn-primary w-full py-3" @click="confirmPicker()">Səbətə əlavə et · <span x-text="money(pickerPrice * picker.quantity)"></span></button>
            </div>
        </template>
    </x-modal>

    {{-- Endirim --}}
    <x-modal show="modal === 'discount'" close="modal = null" title="Endirim" width="max-w-sm">
        <div class="mb-3 grid grid-cols-2 gap-2">
            <button class="btn" :class="discountType === 'percentage' ? 'btn-primary' : 'btn-secondary'" @click="discountType = 'percentage'"><x-icon name="percent" class="size-4" /> Faiz</button>
            <button class="btn" :class="discountType === 'fixed' ? 'btn-primary' : 'btn-secondary'" @click="discountType = 'fixed'"><x-icon name="tag" class="size-4" /> Məbləğ</button>
        </div>
        <input type="number" min="0" step="0.01" x-model.number="discountValue" class="input text-lg" :placeholder="discountType === 'percentage' ? '%' : currency">
        <div class="mt-2 flex gap-2" x-show="discountType === 'percentage'">
            <template x-for="p in [5, 10, 15, 20]"><button class="chip" @click="discountValue = p" x-text="p + '%'"></button></template>
        </div>
        <x-slot:footer>
            <button class="btn-secondary" @click="discountValue = 0; modal = null">Sıfırla</button>
            <button class="btn-primary" @click="modal = null">Tətbiq et</button>
        </x-slot:footer>
    </x-modal>

    {{-- Ödəniş --}}
    <x-modal show="modal === 'pay'" close="modal = null" title="Ödəniş al və mətbəxə göndər">
        <dl class="space-y-1.5 rounded-xl bg-zinc-50 p-4 text-sm">
            <div class="flex justify-between"><dt>Ara cəm</dt><dd x-text="money(totals.sub)"></dd></div>
            <div class="flex justify-between"><dt>Endirim</dt><dd x-text="'−' + money(totals.discount)"></dd></div>
            <div class="flex justify-between" x-show="taxEnabled"><dt>Vergi</dt><dd x-text="money(totals.tax)"></dd></div>
            <div class="flex justify-between" x-show="totals.service > 0"><dt>Servis haqqı</dt><dd x-text="money(totals.service)"></dd></div>
            <div class="flex justify-between text-amber-700" x-show="totals.topup > 0"><dt x-text="'Depozitə qədər fərq (min. ' + money(totals.deposit) + ')'"></dt><dd x-text="money(totals.topup)"></dd></div>
            <div class="flex justify-between border-t border-zinc-200 pt-2 text-xl font-black"><dt>Cəm</dt><dd x-text="money(totals.total)"></dd></div>
        </dl>
        <p class="label mt-4">Ödəniş növü</p>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <template x-for="p in paymentTypes" :key="p.id">
                <button class="flex flex-col items-center gap-1 rounded-xl border px-3 py-3 text-sm font-bold transition" :class="paymentTypeId === p.id ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-zinc-200'" @click="paymentTypeId = p.id" x-text="p.title"></button>
            </template>
        </div>
        <x-slot:footer>
            <button class="btn-secondary" @click="modal = null">Ləğv</button>
            <button class="btn-primary" :disabled="busy || !paymentTypeId" @click="submit('pay')">Ödənişi təsdiqlə</button>
        </x-slot:footer>
    </x-modal>

    {{-- Qaralama adı --}}
    <x-modal show="modal === 'draft'" close="modal = null" title="Qaralama kimi saxla" width="max-w-sm">
        <label class="label">Ad / istinad (istəyə bağlı)</label>
        <input x-model="draftReference" class="input" placeholder="Məs. Elvin — telefon sifarişi" @keydown.enter="submit('draft')">
        <x-slot:footer>
            <button class="btn-secondary" @click="modal = null">Bağla</button>
            <button class="btn-primary" :disabled="busy" @click="submit('draft')">Saxla</button>
        </x-slot:footer>
    </x-modal>

    {{-- Qaralamalar --}}
    <x-modal show="modal === 'drafts'" close="modal = null" title="Qaralamalar">
        <template x-for="d in drafts" :key="d.id">
            <div class="flex items-center justify-between border-b border-zinc-100 py-2.5">
                <div>
                    <p class="font-bold" x-text="d.reference || ('Qaralama #' + d.id)"></p>
                    <p class="text-xs text-zinc-500" x-text="d.created_at + ' · ' + d.items.length + ' məhsul' + (d.customer ? ' · ' + d.customer.name : '')"></p>
                </div>
                <div class="flex gap-1">
                    <button class="btn-primary btn-sm" @click="loadDraft(d)">Aç</button>
                    <button class="btn-ghost btn-sm text-red-600" @click="deleteDraft(d)"><x-icon name="trash" class="size-4" /></button>
                </div>
            </div>
        </template>
        <p x-show="!drafts.length" class="py-8 text-center text-sm text-zinc-500">Qaralama yoxdur.</p>
    </x-modal>

    {{-- QR sifarişlər --}}
    <x-modal show="modal === 'qr'" close="modal = null" title="QR menyudan gələn sifarişlər">
        <template x-for="o in qrOrders" :key="o.id">
            <div class="border-b border-zinc-100 py-3">
                <p class="flex justify-between font-bold"><span x-text="'Token #' + o.token_no + ' · ' + (o.table || '')"></span><span class="text-sm text-zinc-500" x-text="o.time"></span></p>
                <ul class="mt-1 text-sm text-zinc-600"><template x-for="i in o.items"><li x-text="i"></li></template></ul>
                <p class="mt-1 text-xs text-amber-700" x-show="o.notes" x-text="o.notes"></p>
            </div>
        </template>
        <p x-show="!qrOrders.length" class="py-8 text-center text-sm text-zinc-500">Bu gün yeni QR sifariş yoxdur.</p>
        <x-slot:footer><a href="{{ route('orders.index') }}" class="btn-primary">Sifarişlər ekranına keç</a></x-slot:footer>
    </x-modal>

    {{-- PIN-dən sonra: POS və ya Masalar (tam ekran, sensor üçün böyük düymələr) --}}
    <div x-show="view === 'home'" x-cloak class="fixed inset-0 z-[60] flex flex-col bg-ink p-4 text-white [touch-action:manipulation] sm:p-8">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold tracking-widest text-white/50 uppercase">Xoş gəldiniz</p>
                <p class="text-3xl font-black sm:text-4xl" x-text="$store.terminal.operator?.name ?? ''"></p>
                <p class="text-white/60" x-text="$store.terminal.operator?.role ?? ''"></p>
            </div>
            <button type="button" x-show="$store.terminal.active" class="flex items-center gap-2 rounded-2xl bg-white/10 px-5 py-4 text-lg font-bold active:bg-white/25" @click="$store.terminal.lock()">
                <x-icon name="logout" class="size-6" /> Kilidlə
            </button>
        </div>
        <div class="mt-6 grid min-h-0 flex-1 grid-cols-1 gap-4 sm:mt-8 sm:grid-cols-2 sm:gap-6">
            <button type="button" class="flex flex-col items-center justify-center gap-5 rounded-3xl bg-brand-600 transition-colors select-none active:bg-brand-700" @click="view = null">
                <x-icon name="pos" class="size-[clamp(4rem,14vh,9rem)]" />
                <span class="text-[clamp(2rem,6vh,4rem)] leading-none font-black">POS</span>
                <span class="text-lg text-white/70">Yeni sifariş, səbət, ödəniş</span>
            </button>
            <button type="button" class="flex flex-col items-center justify-center gap-5 rounded-3xl bg-white/[0.08] transition-colors select-none active:bg-white/20" @click="openTables()">
                <x-icon name="table" class="size-[clamp(4rem,14vh,9rem)]" />
                <span class="text-[clamp(2rem,6vh,4rem)] leading-none font-black">Masalar</span>
                <span class="text-lg text-white/70">Zal planı — masanı seçin</span>
            </button>
        </div>
    </div>

    {{-- Masa planı: yuxarıdan görünüş, oturacaqlar tutuma görə; masaya toxunanda POS həmin masa ilə açılır --}}
    <div x-show="view === 'tables'" x-cloak class="fixed inset-0 z-[60] flex flex-col bg-canvas [touch-action:manipulation]">
        <header class="flex flex-wrap items-center gap-3 border-b border-zinc-200 bg-white px-4 py-3 sm:px-6">
            <h2 class="text-2xl font-black">Masalar</h2>
            <div class="flex flex-wrap gap-2">
                <template x-for="f in floors" :key="f">
                    <button class="chip px-5 py-2.5 text-base" :class="mapFloor === f && 'active'" @click="mapFloor = f" x-text="f"></button>
                </template>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <button type="button" class="btn-secondary px-5 py-3 text-base" @click="loadBoard()"><x-icon name="refresh" class="size-5" /></button>
                <button type="button" x-show="$store.terminal.active" class="btn-secondary px-5 py-3 text-base" @click="$store.terminal.lock()"><x-icon name="logout" class="size-5" /> Kilidlə</button>
                <button type="button" class="btn-primary px-6 py-3 text-base" @click="view = null"><x-icon name="pos" class="size-5" /> POS</button>
            </div>
        </header>
        <div class="flex flex-wrap gap-4 px-4 pt-3 text-sm text-zinc-600 sm:px-6">
            <span class="flex items-center gap-1.5"><span class="size-4 rounded border-2 border-zinc-300 bg-white"></span> Boş</span>
            <span class="flex items-center gap-1.5"><span class="size-4 rounded bg-amber-400"></span> Dolu</span>
            <span class="flex items-center gap-1.5"><span class="size-4 rounded bg-brand-500"></span> Hazır sifariş var</span>
            <span class="flex items-center gap-1.5"><span class="badge-amber">D</span> Depozitli kabinet</span>
        </div>
        {{-- Zal planı (Ayarlar → Masalar → Zal planı). Boş masa → POS; dolu masa → əməliyyat paneli --}}
        <div class="relative min-h-0 flex-1 p-3 sm:p-5">
            <div class="relative h-full w-full overflow-hidden rounded-3xl bg-white ring-1 ring-zinc-200">
                <template x-for="t in mapTables" :key="t.id">
                    <button type="button" @click="tapTable(t)" class="absolute flex -translate-x-1/2 -translate-y-1/2 flex-col items-center rounded-2xl p-2 transition select-none active:scale-95"
                            :class="String(tableId) === String(t.id) && deliveryType === 'dinein' ? 'bg-brand-50 ring-4 ring-brand-400' : ''"
                            :style="`left: ${t.x}%; top: ${t.y}%`">
                        <span x-show="t.deposit > 0" class="badge-amber absolute -top-1 -left-1" x-text="'D ' + money(t.deposit)"></span>
                        <span x-show="tableState(t)?.ready" class="badge-green absolute -top-1 -right-1">Hazır</span>
                        <x-table-tile state="tableState(t)" />
                        <p class="mt-1 max-w-48 truncate text-xs" :class="tableState(t) ? 'font-semibold text-zinc-700' : 'font-bold text-brand-600'"
                           x-text="tableState(t) ? (tableState(t).since + ' · ' + tableState(t).orders + ' sif.' + (tableState(t).waiter ? ' · ' + tableState(t).waiter : '')) : 'Boş'"></p>
                    </button>
                </template>
                <template x-if="!tables.length"><div class="absolute inset-0 flex items-center justify-center"><x-empty icon="table" title="Masa yoxdur">Ayarlar → Masalar bölməsində masa əlavə edin.</x-empty></div></template>
            </div>
        </div>

        {{-- Dolu masa: əlavə sifariş, hesab çeki, hesabı bağla --}}
        <div x-show="tableSheet" x-cloak class="absolute inset-0 z-10 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-6" @click.self="tableSheet = null">
            <template x-if="tableSheet">
                <div class="flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-t-3xl bg-white sm:rounded-3xl">
                    <div class="flex items-start justify-between border-b border-zinc-100 px-6 py-5">
                        <div>
                            <p class="text-2xl font-black" x-text="tableSheet.table.label"></p>
                            <p class="text-sm text-zinc-500" x-text="tableSheet.group.orders.length + ' sifariş · ' + tableSheet.group.orders[0].time + '-dən' + (tableSheet.group.orders[0].waiter ? ' · ' + tableSheet.group.orders[0].waiter : '')"></p>
                        </div>
                        <button type="button" class="btn-ghost" @click="tableSheet = null"><x-icon name="x" class="size-6" /></button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                        <template x-for="o in tableSheet.group.orders" :key="o.id">
                            <div class="mb-3">
                                <p class="mb-1 text-xs font-bold tracking-wide text-zinc-400 uppercase" x-text="'Token #' + o.token_no + ' · ' + o.time + ' · ' + o.status_label"></p>
                                <template x-for="i in o.items"><p class="flex justify-between text-base" :class="i.status === 'cancelled' && 'line-through text-zinc-400'"><span x-text="i.qty + ' × ' + i.title + (i.variant ? ' (' + i.variant + ')' : '')"></span></p></template>
                            </div>
                        </template>
                        <div class="mt-2 rounded-2xl bg-zinc-50 p-4">
                            <p class="flex justify-between text-sm text-zinc-600" x-show="tableSheet.group.deposit > 0"><span x-text="'Sifariş cəmi · depozit ' + money(tableSheet.group.deposit)"></span><span x-text="money(tableSheet.group.orders_total)"></span></p>
                            <p class="flex justify-between text-sm text-amber-700" x-show="tableSheet.group.deposit_topup > 0"><span>Depozitə qədər fərq</span><span x-text="money(tableSheet.group.deposit_topup)"></span></p>
                            <p class="flex justify-between text-2xl font-black"><span>Ödəniləcək</span><span x-text="money(tableSheet.group.due)"></span></p>
                        </div>
                    </div>
                    <div x-show="!tableSheet.paying" class="grid grid-cols-2 gap-3 border-t border-zinc-100 p-4">
                        <button type="button" class="btn-secondary py-4 text-base" @click="pickTable(tableSheet.table)"><x-icon name="plus" class="size-5" /> Əlavə sifariş</button>
                        <button type="button" class="btn-secondary py-4 text-base" @click="printBill()"><x-icon name="printer" class="size-5" /> Hesab çeki</button>
                        <button type="button" x-show="$store.terminal.canPay" class="btn-primary col-span-2 py-5 text-lg" @click="tableSheet.paying = true"><x-icon name="cash" class="size-6" /> Hesabı bağla · <span x-text="money(tableSheet.group.due)"></span></button>
                        <p x-show="!$store.terminal.canPay" class="col-span-2 text-center text-sm text-zinc-500">Hesabı kassir bağlayır — "Hesab çeki"ni müştəriyə aparın.</p>
                    </div>
                    <div x-show="tableSheet.paying" class="border-t border-zinc-100 p-4">
                        <p class="mb-3 text-center font-bold">Ödəniş növü</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <template x-for="p in paymentTypes" :key="p.id">
                                <button type="button" class="rounded-2xl bg-brand-500 py-5 text-lg font-black text-white active:bg-brand-700" @click="closeBill(p.id)" x-text="p.title"></button>
                            </template>
                        </div>
                        <button type="button" class="btn-ghost mt-3 w-full" @click="tableSheet.paying = false">← Geri</button>
                    </div>
                </div>
            </template>
        </div>
    </div>
    {{-- Müştəri ekranı seçimi --}}
    <x-modal show="modal === 'display'" close="modal = null" title="Müştəri ekranı" width="max-w-md">
        <p class="mb-3 text-sm text-zinc-500">Bu kassanın müştəriyə baxan ekranını seçin. Səbət dəyişdikcə müştəri məhsulları və cəmi canlı görəcək. Seçim bu cihazda yadda qalır.</p>
        <div class="space-y-2">
            <template x-for="d in displays" :key="d.id">
                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-zinc-200 px-4 py-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                    <span class="flex items-center gap-3"><input type="radio" name="display" :checked="displayId === d.id" @change="selectDisplay(d.id)" class="text-brand-500"> <span class="font-bold" x-text="d.name"></span></span>
                </label>
            </template>
            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3 text-sm text-zinc-600 has-[:checked]:border-zinc-400">
                <input type="radio" name="display" :checked="!displayId" @change="selectDisplay(null)"> Bu cihazda müştəri ekranı istifadə etmə
            </label>
        </div>
        <p x-show="!displays.length" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800">Hələ müştəri ekranı yaradılmayıb.</p>
        <x-slot:footer>
            @if (auth()->user()->isAdmin() || auth()->user()->hasScope('SETTINGS'))
                <a href="{{ route('settings.displays.index') }}" class="btn-ghost mr-auto">Ekranları idarə et</a>
            @endif
            <button class="btn-secondary" x-show="display" @click="openDisplayWindow()"><x-icon name="pos" class="size-4" /> Bu kompüterdə aç</button>
            <button class="btn-primary" @click="modal = null">Hazır</button>
        </x-slot:footer>
    </x-modal>

    {{-- Yeni müştəri --}}
    <x-modal show="modal === 'customer'" close="modal = null" title="Yeni müştəri">
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="label">Telefon *</label><input x-model="newCustomer.phone" class="input" placeholder="+994"></div>
            <div><label class="label">Ad *</label><input x-model="newCustomer.name" class="input"></div>
            <div><label class="label">Email</label><input x-model="newCustomer.email" type="email" class="input"></div>
            <div><label class="label">Doğum tarixi</label><input x-model="newCustomer.birth_date" type="date" class="input"></div>
            <div class="sm:col-span-2"><label class="label">Cins</label>
                <select x-model="newCustomer.gender" class="input"><option value="">—</option><option value="female">Qadın</option><option value="male">Kişi</option><option value="other">Digər</option></select>
            </div>
        </div>
        <x-slot:footer>
            <button class="btn-secondary" @click="modal = null">Bağla</button>
            <button class="btn-primary" @click="saveCustomer()">Saxla</button>
        </x-slot:footer>
    </x-modal>

    {{-- Uğurlu nəticə --}}
    <x-modal show="modal === 'done'" close="modal = null" width="max-w-sm">
        <div class="py-4 text-center">
            <div class="mx-auto mb-3 flex size-14 items-center justify-center rounded-full bg-brand-100 text-brand-600"><x-icon name="check" class="size-7" /></div>
            <p class="text-lg font-black" x-text="result?.message"></p>
            <p class="mt-2 text-sm text-zinc-500">Token</p>
            <p class="text-5xl font-black text-brand-600" x-text="'#' + result?.order.token_no"></p>
        </div>
        <x-slot:footer>
            <button class="btn-secondary" @click="printToken()"><x-icon name="printer" class="size-4" /> Token</button>
            <button class="btn-secondary" x-show="result?.invoice_id" @click="printReceipt()"><x-icon name="invoice" class="size-4" /> Qəbz</button>
            <button class="btn-primary" @click="modal = null">Yeni sifariş</button>
        </x-slot:footer>
    </x-modal>
</div>
<x-terminal-lock :state="$posData['terminal']" />
</x-layouts.app>
