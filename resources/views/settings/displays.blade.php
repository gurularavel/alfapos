<x-layouts.app title="Müştəri ekranları">
<div x-data="{ edit: null }">
    <x-page-header title="Müştəri ekranları" subtitle="Kassada müştəriyə baxan ikinci ekran: səbət, cəm və təşəkkür mesajı canlı göstərilir.">
        <a href="{{ route('settings.index') }}" class="btn-secondary">← Ayarlar</a>
        <button class="btn-primary" @click="edit = { id: null, name: 'Kassa {{ $displays->count() + 1 }}' }"><x-icon name="plus" class="size-4" /> Yeni ekran</button>
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($displays as $d)
            <div class="card card-pad flex flex-col">
                <div class="mb-3 flex items-start justify-between">
                    <div>
                        <p class="text-lg font-extrabold">{{ $d->name }}</p>
                        <p class="text-xs text-zinc-500">{{ $d->isOnline() ? 'Qoşuludur' : ($d->last_seen_at ? 'Son görünmə: '.$d->last_seen_at->diffForHumans() : 'Hələ açılmayıb') }}</p>
                    </div>
                    <span @class(['size-3 rounded-full', 'bg-brand-500' => $d->isOnline(), 'bg-zinc-300' => ! $d->isOnline()])></span>
                </div>
                <div class="flex gap-4">
                    <img src="{{ (new \chillerlan\QRCode\QRCode)->render($d->url()) }}" alt="QR" class="size-28 shrink-0 rounded-lg border border-zinc-100">
                    <div class="min-w-0 text-sm text-zinc-600">
                        <p>Müştəri ekranı olacaq cihazda (planşet və ya ikinci monitor) bu linki açın və ya QR-ı skan edin. Tam ekran üçün ekrana iki dəfə toxunun.</p>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2 rounded-xl bg-zinc-50 p-2" x-data="{ copied: false }">
                    <input readonly value="{{ $d->url() }}" class="min-w-0 flex-1 bg-transparent text-xs text-zinc-600 outline-none" @focus="$el.select()">
                    <button type="button" class="btn-secondary btn-sm" @click="navigator.clipboard.writeText(@js($d->url())); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Kopyalandı' : 'Kopyala'"></button>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ $d->url() }}" target="_blank" class="btn-secondary btn-sm"><x-icon name="pos" class="size-4" /> Aç</a>
                    <button class="btn-ghost btn-sm" @click="edit = @js(['id' => $d->id, 'name' => $d->name])"><x-icon name="edit" class="size-4" /> Dəyiş</button>
                    <form method="POST" action="{{ route('settings.displays.destroy', $d) }}" onsubmit="return confirm('Ekran silinsin? Link işləməz olacaq.')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /> Sil</button></form>
                </div>
            </div>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty icon="pos" title="Müştəri ekranı yoxdur">Kassa üçün ekran yaradın, sonra POS-da həmin ekranı seçin — müştəri səbəti canlı görəcək.</x-empty></div>
        @endforelse
    </div>

    <div class="card card-pad mt-4 text-sm text-zinc-600">
        <p class="mb-1 font-extrabold text-ink">Necə qoşulur?</p>
        <ol class="list-decimal space-y-1 pl-4">
            <li>Burada ekran yaradın və linki müştəriyə baxan cihazda açın (eyni kompüterin ikinci monitorunda da olar).</li>
            <li>POS-da yuxarıdakı <b>"Müştəri ekranı"</b> düyməsini basıb həmin ekranı seçin — seçim bu cihazda yadda qalır.</li>
            <li>Səbət dəyişdikcə müştəri məhsulları və cəmi görür; ödənişdən sonra "Təşəkkür edirik" və token nömrəsi göstərilir; boş vaxtda ekran qoruyucu şəkillər (<a href="{{ route('settings.terminal') }}" class="text-brand-700 underline">POS terminalı</a> ayarından) göstərilir.</li>
        </ol>
    </div>

    <x-modal show="edit" close="edit = null" title="Müştəri ekranı" width="max-w-md">
        <template x-if="edit"><form method="POST" :action="edit.id ? @js(url('dashboard/settings/displays')) + '/' + edit.id : @js(route('settings.displays.store'))" id="display-form" class="space-y-3">
            @csrf
            <template x-if="edit.id"><input type="hidden" name="_method" value="PUT"></template>
            <div><label class="label">Ad *</label><input name="name" x-model="edit.name" class="input" required maxlength="60"></div>
            <label x-show="edit.id" class="flex items-start gap-2 text-sm"><input type="checkbox" name="regenerate" value="1" class="mt-0.5 rounded text-red-500"> <span>Yeni link yarat <span class="block text-xs text-zinc-500">Köhnə link dərhal işləməz olur (link başqasının əlinə keçibsə).</span></span></label>
        </form></template>
        <x-slot:footer><button class="btn-secondary" @click="edit = null">Bağla</button><button class="btn-primary" form="display-form">Saxla</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
