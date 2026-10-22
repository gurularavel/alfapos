<x-layouts.app title="POS terminalı">
    <x-page-header title="POS terminalı" subtitle="Ekran qoruyucu, PIN ilə giriş və avtomatik kilid.">
        <a href="{{ route('settings.index') }}" class="btn-secondary">← Ayarlar</a>
    </x-page-header>

    @if ($staffWithoutPin->isNotEmpty())
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            PIN-i olmayan işçilər terminalda işləyə bilməz: <b>{{ $staffWithoutPin->implode(', ') }}</b>.
            <a href="{{ route('users.index') }}" class="font-semibold underline">İstifadəçilər</a> bölməsində PIN təyin edin.
        </div>
    @endif

    <form method="POST" action="{{ route('settings.terminal.update') }}" enctype="multipart/form-data" class="grid gap-4 xl:grid-cols-3">
        @csrf @method('PUT')
        <div class="card card-pad space-y-4 xl:col-span-2">
            <div>
                <p class="font-extrabold">Ekran qoruyucu şəkilləri</p>
                <p class="text-sm text-zinc-500">Kilidli POS ekranında növbə ilə göstərilir (reklam, kampaniya, menyu şəkilləri). Toxunanda PIN soruşulur. Maksimum {{ \App\Http\Controllers\Settings\TerminalController::MAX_IMAGES }} şəkil, hər biri 4 MB-a qədər; üfüqi (16:9) şəkillər ən yaxşı görünür.</p>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @forelse ($images as $i => $url)
                    <div class="group relative aspect-video overflow-hidden rounded-xl bg-zinc-100">
                        <img src="{{ $url }}" alt="" class="size-full object-cover">
                        <button form="del-{{ $i }}" class="absolute top-2 right-2 flex size-8 items-center justify-center rounded-full bg-white/90 text-red-600 shadow" title="Sil"><x-icon name="trash" class="size-4" /></button>
                    </div>
                @empty
                    <div class="col-span-full rounded-xl border-2 border-dashed border-zinc-200 p-6 text-center text-sm text-zinc-500">Şəkil yoxdur — ekranda mağaza adı, loqo və saat göstəriləcək.</div>
                @endforelse
            </div>
            <div x-data="{ previews: [] }">
                <label class="label">Şəkil əlavə et</label>
                <input type="file" name="images[]" accept="image/*" multiple class="text-sm" @change="previews = [...$event.target.files].map(f => URL.createObjectURL(f))">
                <div class="mt-2 flex flex-wrap gap-2"><template x-for="p in previews"><img :src="p" class="h-16 rounded-lg object-cover"></template></div>
                @error('images')<p class="field-error">{{ $message }}</p>@enderror
                @error('images.*')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="space-y-4">
            <div class="card card-pad space-y-4" x-data="{ t: {{ (int) old('lock_timeout', $store->terminalSetting('lock_timeout')) }} }">
                <p class="font-extrabold">Avtomatik kilid</p>
                <div>
                    <label class="label">Hərəkətsizlikdən sonra kilidlə</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="lock_timeout" min="10" max="3600" x-model.number="t" class="input w-28">
                        <span class="text-sm text-zinc-500">saniyə</span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ([20 => '20 san', 30 => '30 san', 60 => '1 dəq', 120 => '2 dəq', 300 => '5 dəq'] as $sec => $label)
                            <button type="button" class="chip" :class="t === {{ $sec }} && 'active'" @click="t = {{ $sec }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    @error('lock_timeout')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="lock_after_order" value="1" @checked($store->terminalSetting('lock_after_order')) class="mt-0.5 rounded text-brand-500">
                    <span>Sifariş göndəriləndən / ödəniş alınandan sonra dərhal kilidlə<br><span class="text-xs text-zinc-500">Növbəti işçi öz PIN-i ilə davam edir.</span></span>
                </label>
            </div>
            <div class="card card-pad space-y-3" x-data>
                <p class="font-extrabold">Səslər</p>
                <label class="flex items-start justify-between gap-3 text-sm">
                    <span>Məhsul səbətə əlavə olunanda səs<br><span class="text-xs text-zinc-500">Kassir/ofisiant hər toxunuşun qəbul olunduğunu eşidir.</span></span>
                    <input type="checkbox" name="sound_add_item" value="1" @checked($store->terminalSetting('sound_add_item')) class="mt-0.5 size-5 rounded text-brand-500">
                </label>
                <label class="flex items-start justify-between gap-3 text-sm">
                    <span>Yeni sifariş siqnalı<br><span class="text-xs text-zinc-500">Mətbəx ekranında yeni sifariş, POS-da yeni QR sifariş gələndə.</span></span>
                    <input type="checkbox" name="sound_new_order" value="1" @checked($store->terminalSetting('sound_new_order')) class="mt-0.5 size-5 rounded text-brand-500">
                </label>
                <div class="flex gap-2">
                    <button type="button" class="btn-secondary btn-sm" @click="playSound('add')">▶ Əlavə səsi</button>
                    <button type="button" class="btn-secondary btn-sm" @click="playSound('alert')">▶ Sifariş siqnalı</button>
                </div>
            </div>
            <div class="card card-pad text-sm text-zinc-600">
                <p class="mb-1 font-extrabold text-ink">Necə işləyir?</p>
                <ol class="list-decimal space-y-1 pl-4">
                    <li>Admin cihazda daxil olur və POS-da <b>"Terminal rejimi"</b> düyməsini basır.</li>
                    <li>Ekran kilidlənir, şəkillər göstərilir.</li>
                    <li>İşçi ekrana toxunur, PIN-ini yazır — sifarişlər onun adına yazılır.</li>
                    <li>Terminal rejimindən çıxmaq üçün admin PIN-i lazımdır.</li>
                </ol>
            </div>
            <button class="btn-primary w-full py-3">Yadda saxla</button>
        </div>
    </form>

    @foreach ($images as $i => $url)
        <form id="del-{{ $i }}" method="POST" action="{{ route('settings.terminal.images.destroy', $i) }}" onsubmit="return confirm('Şəkil silinsin?')">@csrf @method('DELETE')</form>
    @endforeach
</x-layouts.app>
