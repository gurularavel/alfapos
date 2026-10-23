{{-- Oxunmamış stok bildirişi varsa header altında yanıb-sönən zolaq --}}
<div x-data x-cloak x-show="$store.alerts?.allowed && $store.alerts?.banner" class="no-print alert-blink flex items-center gap-3 border-b px-4 py-2 text-sm font-semibold sm:px-6"
     :class="$store.alerts?.out ? 'border-red-200 text-red-800' : 'border-amber-200 text-amber-900'"
     :style="$store.alerts?.out ? '--blink-from:#fecaca;--blink-to:#fff1f2' : '--blink-from:#fde68a;--blink-to:#fffbeb'">
    <x-icon name="alert" class="size-5 shrink-0" />
    <span class="min-w-0 flex-1 truncate" x-text="$store.alerts?.banner"></span>
    <button type="button" x-show="!$store.terminal?.active" class="shrink-0 rounded-lg bg-white/70 px-3 py-1 text-xs font-bold hover:bg-white" @click="$store.alerts.open = true">Bax</button>
    <button type="button" class="shrink-0 text-xs font-bold underline" @click="$store.alerts.markRead()">Oxundu</button>
</div>
