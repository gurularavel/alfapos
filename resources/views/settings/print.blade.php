<x-layouts.app title="Çap ayarları">
    <x-page-header title="Çap ayarları" subtitle="Dəyişikliklər sağdakı nümunədə dərhal görünür; yadda saxlamadan əvvəl nümunəni çap edib yoxlaya bilərsiniz.">
        <a href="{{ route('settings.index') }}" class="btn-secondary">← Ayarlar</a>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]"
         x-data="{
            type: 'receipt',
            src: '',
            timer: null,
            url(extra = {}) {
                const data = new FormData($refs.form);
                const q = new URLSearchParams({ type: this.type, ...extra });
                for (const k of ['paper_width', 'header', 'footer']) q.set(k, data.get(k) ?? '');
                for (const k of @js(['show_store_details', 'show_customer_details', 'show_notes', 'print_token', 'kot_show_prices', 'auto_print'])) q.set(k, data.get(k) ? 1 : 0);
                return @js(route('settings.print.preview')) + '?' + q.toString();
            },
            refresh() { clearTimeout(this.timer); this.timer = setTimeout(() => this.src = this.url(), 250) },
            printSample() { printUrl(this.url({ print: 1 })) },
         }"
         x-init="refresh(); $watch('type', () => refresh())">

        <form x-ref="form" method="POST" action="{{ route('settings.print.update') }}" class="card card-pad space-y-4 self-start" @input="refresh()" @change="refresh()">
            @csrf @method('PUT')
            <div>
                <p class="label">Kağız eni</p>
                <div class="flex gap-2">
                    @foreach ([80, 58] as $w)
                        <label class="cursor-pointer rounded-xl border border-zinc-200 px-5 py-2.5 text-sm font-bold has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50"><input type="radio" name="paper_width" value="{{ $w }}" @checked($store->printSetting('paper_width', 80) == $w) class="sr-only">{{ $w }} mm</label>
                    @endforeach
                </div>
            </div>
            <div><label class="label">Qəbz başlığı</label><input name="header" value="{{ $store->printSetting('header') }}" class="input" placeholder="{{ $store->name }}"></div>
            <div><label class="label">Qəbz sonluğu</label><input name="footer" value="{{ $store->printSetting('footer') }}" class="input"></div>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach (['show_store_details' => 'Mağaza məlumatlarını göstər', 'show_customer_details' => 'Müştəri məlumatlarını göstər', 'show_notes' => 'Qeydləri göstər', 'print_token' => 'Mətbəx çekini (token) avtomatik çap et', 'kot_show_prices' => 'Mətbəx çekində qiymət', 'auto_print' => 'Ödənişdən sonra avtomatik çap'] as $flag => $label)
                    <label class="flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm"><input type="checkbox" name="{{ $flag }}" value="1" @checked($store->printSetting($flag)) class="rounded text-brand-500"> {{ $label }}</label>
                @endforeach
            </div>
            <button class="btn-primary">Yadda saxla</button>
        </form>

        <div class="space-y-3 xl:sticky xl:top-20 xl:self-start">
            <div class="flex items-center justify-between gap-2">
                <div class="flex gap-1 rounded-xl bg-zinc-100 p-1">
                    <button type="button" class="rounded-lg px-4 py-2 text-sm font-bold" :class="type === 'receipt' ? 'bg-white shadow-sm' : 'text-zinc-500'" @click="type = 'receipt'">Qəbz</button>
                    <button type="button" class="rounded-lg px-4 py-2 text-sm font-bold" :class="type === 'kot' ? 'bg-white shadow-sm' : 'text-zinc-500'" @click="type = 'kot'">Mətbəx çeki</button>
                </div>
                <button type="button" class="btn-secondary" @click="printSample()"><x-icon name="printer" class="size-4" /> Nümunəni çap et</button>
            </div>
            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-200">
                <iframe :src="src" title="Çek nümunəsi" class="h-[70vh] w-full bg-zinc-200"></iframe>
            </div>
            <p class="text-xs text-zinc-500">Nümunə məlumatlarla göstərilir — heç nə bazaya yazılmır. Mağaza adı, ünvan və loqo "Mağaza məlumatları"ndan, vergi və servis haqqı öz ayarlarından götürülür.</p>
        </div>
    </div>
</x-layouts.app>
