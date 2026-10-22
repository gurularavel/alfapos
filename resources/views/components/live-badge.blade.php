{{-- Reverb bağlantısının göstəricisi: yaşıl = canlı, boz = ehtiyat rejim (vaxtaşırı yoxlama) --}}
<span x-data class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
      :class="$store.rt.connected ? 'bg-brand-100 text-brand-700' : 'bg-zinc-100 text-zinc-500'"
      :title="$store.rt.connected ? 'Dəyişikliklər dərhal görünür' : 'Canlı bağlantı yoxdur — ekran vaxtaşırı yenilənir'">
    <span class="size-2 rounded-full" :class="$store.rt.connected ? 'animate-pulse bg-brand-500' : 'bg-zinc-400'"></span>
    <span x-text="$store.rt.connected ? 'Canlı' : 'Ehtiyat rejim'"></span>
</span>
