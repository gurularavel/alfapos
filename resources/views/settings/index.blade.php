<x-layouts.app title="Ayarlar">
    <x-page-header title="Ayarlar" subtitle="Müəssisənizi konfiqurasiya edin." />
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['settings.store', 'store', 'Mağaza məlumatları', 'Ad, loqo, ünvan, valyuta, QR menyu və rəy ayarları.'],
            ['settings.branches.index', 'building', 'Filiallar və terminallar', 'Filiallar, kassa/POS cihazları, cihazın terminala bağlanması, terminala bağlı işçilər.'],
            ['settings.terminal', 'pos', 'POS terminalı və səslər', 'Ekran qoruyucu, PIN ilə giriş, avtomatik kilid vaxtı, POS və mətbəx səsləri.'],
            ['settings.displays.index', 'pos', 'Müştəri ekranları', 'Kassada müştəriyə baxan ikinci ekran: səbət və cəm canlı göstərilir.'],
            ['settings.print', 'printer', 'Çap ayarları', 'Qəbz başlığı/sonluğu, kağız eni (58/80mm), mətbəx çeki (KOT).'],
            ['settings.tables.index', 'table', 'Masalar', 'Masalar, mərtəbələr, tutum və hər masanın QR kodu.'],
            ['settings.menu-items.index', 'orders', 'Menyu', 'Məhsullar, variantlar, əlavələr, şəkillər və reseptlər.'],
            ['settings.categories.index', 'tag', 'Kateqoriyalar', 'Menyu kateqoriyaları və sıralama.'],
            ['settings.taxes.index', 'percent', 'Vergi və servis haqqı', 'Vergini və servis haqqını aç/söndür, faiz, "yalnız zalda" qaydası, vergi dərəcələri.'],
            ['settings.payment-types.index', 'card', 'Ödəniş növləri', 'Nağd, kart, onlayn və digər ödəniş üsulları.'],
            ['settings.online-payment', 'online', 'Onlayn ödəniş', 'Kapital Bank: müştəri masanın ödəniş QR-ı ilə hesabı kartla ödəyir.'],
            ['subscription', 'crown', 'Abunəlik', 'Planınız, limitlər və ödəniş tarixçəsi.'],
        ] as [$route, $icon, $title, $desc])
            <a href="{{ route($route) }}" class="card card-pad group flex gap-4 transition hover:border-brand-300">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon :name="$icon" /></div>
                <div><p class="font-extrabold group-hover:text-brand-700">{{ $title }}</p><p class="mt-1 text-sm text-zinc-500">{{ $desc }}</p></div>
            </a>
        @endforeach
    </div>
</x-layouts.app>
