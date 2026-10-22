<x-layouts.app title="Çap ayarları">
    <x-page-header title="Çap ayarları"><a href="{{ route('settings.index') }}" class="btn-secondary">← Ayarlar</a></x-page-header>
    <form method="POST" action="{{ route('settings.print.update') }}" class="card card-pad max-w-2xl space-y-4">
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
            @foreach (['show_store_details' => 'Mağaza məlumatlarını göstər', 'show_customer_details' => 'Müştəri məlumatlarını göstər', 'show_notes' => 'Qeydləri göstər', 'print_token' => 'Token çap et', 'kot_show_prices' => 'Mətbəx çekində qiymət', 'auto_print' => 'Ödənişdən sonra avtomatik çap'] as $flag => $label)
                <label class="flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm"><input type="checkbox" name="{{ $flag }}" value="1" @checked($store->printSetting($flag)) class="rounded text-brand-500"> {{ $label }}</label>
            @endforeach
        </div>
        <button class="btn-primary">Yadda saxla</button>
    </form>
</x-layouts.app>
