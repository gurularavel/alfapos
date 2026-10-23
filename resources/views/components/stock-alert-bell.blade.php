{{-- Stok bildirişləri zəngi (header). Store: resources/js/stock-alerts.js --}}
@push('scripts')
    <script>window.STOCK_ALERT_URLS = @js(['index' => route('notifications.index'), 'read' => route('notifications.read')]);</script>
@endpush
<div x-data class="relative" @click.outside="$store.alerts && ($store.alerts.open = false)">
    <button type="button" class="relative flex size-10 items-center justify-center rounded-xl hover:bg-zinc-100" aria-label="Bildirişlər"
            :class="$store.alerts?.unread && ($store.alerts.out ? 'text-red-600' : 'text-amber-600')"
            @click="$store.alerts.open = !$store.alerts.open">
        <span :class="$store.alerts?.unread && 'bell-ring'"><x-icon name="bell" class="size-6" /></span>
        <template x-if="$store.alerts?.unread">
            <span class="absolute -top-0.5 -right-0.5 flex size-5">
                <span class="absolute inline-flex size-full animate-ping rounded-full opacity-75" :class="$store.alerts.out ? 'bg-red-400' : 'bg-amber-400'"></span>
                <span class="relative inline-flex size-5 items-center justify-center rounded-full text-[11px] font-black text-white" :class="$store.alerts.out ? 'bg-red-600' : 'bg-amber-500'" x-text="$store.alerts.unread > 9 ? '9+' : $store.alerts.unread"></span>
            </span>
        </template>
    </button>

    <div x-cloak x-show="$store.alerts?.open" x-transition.origin.top.right
         class="absolute right-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3">
            <p class="font-extrabold">Bildirişlər</p>
            <button type="button" class="text-xs font-semibold text-brand-700 hover:underline" x-show="$store.alerts?.unread" @click="$store.alerts.markRead()">Hamısı oxundu</button>
        </div>
        <div class="max-h-96 overflow-y-auto">
            <template x-for="a in $store.alerts?.items ?? []" :key="a.id">
                <a :href="a.url" class="flex items-start gap-3 border-b border-zinc-50 px-4 py-3 last:border-0 hover:bg-zinc-50"
                   :class="a.unread && 'alert-blink'" :style="a.unread && (a.level === 'out' ? '--blink-from:#fee2e2' : '--blink-from:#fef3c7')">
                    <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full text-white" :class="a.level === 'out' ? 'bg-red-600' : 'bg-amber-500'"><x-icon name="alert" class="size-4" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-bold" x-text="a.title"></span>
                        <span class="block text-sm" :class="a.level === 'out' ? 'text-red-700' : 'text-amber-700'" x-text="(a.level === 'out' ? 'Stokda bitdi' : 'Az qalıb') + ' · ' + a.quantity"></span>
                        <span class="block text-xs text-zinc-400" x-text="a.source + ' · ' + a.time"></span>
                    </span>
                    <span x-show="a.unread" class="mt-2 size-2 shrink-0 rounded-full bg-red-500"></span>
                </a>
            </template>
            <div x-show="!($store.alerts?.items ?? []).length" class="px-4 py-10 text-center text-sm text-zinc-500">
                <x-icon name="check" class="mx-auto mb-2 size-8 text-brand-500" /> Stokda problem yoxdur.
            </div>
        </div>
    </div>
</div>
