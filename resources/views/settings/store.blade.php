<x-layouts.app title="Mağaza məlumatları">
    <x-page-header title="Mağaza məlumatları"><a href="{{ route('settings.index') }}" class="btn-secondary">← Ayarlar</a></x-page-header>

    <form method="POST" action="{{ route('settings.store.update') }}" enctype="multipart/form-data" class="grid gap-4 xl:grid-cols-3">
        @csrf @method('PUT')
        <div class="card card-pad space-y-4 xl:col-span-2">
            <div class="flex items-center gap-4">
                <div class="flex size-16 items-center justify-center overflow-hidden rounded-2xl bg-brand-50">
                    @if ($store->logo)<img src="{{ Storage::disk('public')->url($store->logo) }}" alt="" class="size-full object-cover">@else<x-icon name="store" class="size-7 text-brand-400" />@endif
                </div>
                <div><label class="label">Loqo</label><input type="file" name="logo" accept="image/*" class="text-sm"></div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="label">Müəssisənin adı *</label><input name="name" value="{{ old('name', $store->name) }}" class="input" required></div>
                <div class="sm:col-span-2"><label class="label">Ünvan</label><input name="address" value="{{ old('address', $store->address) }}" class="input"></div>
                <div><label class="label">Telefon</label><input name="phone" value="{{ old('phone', $store->phone) }}" class="input"></div>
                <div><label class="label">Email</label><input name="email" type="email" value="{{ old('email', $store->email) }}" class="input"></div>
                <div><label class="label">Valyuta simvolu</label><input name="currency" value="{{ old('currency', $store->currency) }}" class="input" required></div>
                <div><label class="label">Saat qurşağı</label>
                    <select name="timezone" class="input">@foreach (['Asia/Baku', 'Europe/Istanbul', 'Europe/Moscow', 'Asia/Tbilisi', 'Asia/Tashkent', 'Asia/Almaty', 'Europe/London', 'UTC'] as $tz)<option @selected($store->timezone === $tz)>{{ $tz }}</option>@endforeach</select>
                </div>
                <div class="text-sm text-zinc-500"><span class="label">Servis haqqı və vergi</span><a href="{{ route('settings.taxes.index') }}" class="font-semibold text-brand-700 underline">Vergi və servis haqqı</a> bölməsində</div>
                <div><label class="label">Günlük satış hədəfi</label><input name="daily_sales_goal" type="number" step="0.01" min="0" value="{{ old('daily_sales_goal', $store->daily_sales_goal) }}" class="input"></div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card card-pad space-y-3">
                <p class="font-extrabold">QR menyu</p>
                @foreach (['is_qr_menu_enabled' => 'QR menyu aktivdir', 'is_qr_order_enabled' => 'Müştəri QR-dan sifariş verə bilər', 'is_feedback_enabled' => 'Rəy formu aktivdir'] as $flag => $label)
                    <label class="flex items-center justify-between gap-3 text-sm"><span>{{ $label }}</span><input type="checkbox" name="{{ $flag }}" value="1" @checked($store->$flag) class="size-5 rounded text-brand-500"></label>
                @endforeach
                <div class="rounded-xl bg-zinc-50 p-3 text-xs break-all text-zinc-600">
                    Ümumi menyu linki:<br><a href="{{ route('qr.show', $store->qr_code) }}" target="_blank" class="font-semibold text-brand-700 hover:underline">{{ route('qr.show', $store->qr_code) }}</a>
                    <p class="mt-1">Masaya xüsusi QR kodlar <a href="{{ route('settings.tables.index') }}" class="text-brand-700 underline">Masalar</a> bölməsindədir.</p>
                </div>
            </div>
            <button class="btn-primary w-full py-3">Yadda saxla</button>
        </div>
    </form>
</x-layouts.app>
