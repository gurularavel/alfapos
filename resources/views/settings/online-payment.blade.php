<x-layouts.app title="Onlayn ödəniş">
    <x-page-header title="Onlayn ödəniş" subtitle="Kapital Bank: müştəri masadakı ödəniş QR-ı ilə hesabı kartla ödəyir.">
        <a href="{{ route('settings.index') }}" class="btn-secondary">← Ayarlar</a>
    </x-page-header>

    @error('connection')<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $message }}</div>@enderror

    <div class="grid gap-4 xl:grid-cols-3">
        <form method="POST" action="{{ route('settings.online-payment.update') }}" class="card card-pad space-y-4 xl:col-span-2" x-data="{ mode: @js(old('mode', $settings['mode'])) }">
            @csrf @method('PUT')
            <label class="flex items-center gap-3">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" class="size-5 rounded border-zinc-300 text-brand-500" @checked(old('enabled', $settings['enabled']))>
                <span><span class="font-extrabold">Onlayn ödənişi aktiv et</span><br><span class="text-sm text-zinc-500">Kassir POS-da masa panelindən və Sifarişlər ekranından hesabı onlayn ödənişə göndərə bilər.</span></span>
            </label>

            <div>
                <p class="label">Rejim</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-2 rounded-xl border p-3" :class="mode === 'test' ? 'border-brand-500 bg-brand-50' : 'border-zinc-200'">
                        <input type="radio" name="mode" value="test" x-model="mode" class="mt-1 text-brand-500">
                        <span><b>Test</b><br><span class="text-sm text-zinc-500">Bankın test mühiti — real pul çıxılmır. Login boşdursa bankın ümumi test hesabı işlədilir.</span></span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-2 rounded-xl border p-3" :class="mode === 'live' ? 'border-brand-500 bg-brand-50' : 'border-zinc-200'">
                        <input type="radio" name="mode" value="live" x-model="mode" class="mt-1 text-brand-500">
                        <span><b>Canlı</b><br><span class="text-sm text-zinc-500">Real ödənişlər. Kapital Bank ilə e-commerce müqaviləsi və merchant hesabı lazımdır.</span></span>
                    </label>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="login">Merchant login</label>
                    <input id="login" name="login" value="{{ old('login', $settings['login']) }}" class="input" autocomplete="off" :placeholder="mode === 'test' ? 'Boş = bankın test hesabı' : 'Məs. TerminalSys/...'">
                    @error('login')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="password">Parol</label>
                    <input id="password" name="password" type="password" class="input" autocomplete="new-password" placeholder="{{ $settings['password'] ? '•••••••• (dəyişmək üçün yazın)' : '' }}">
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="text-xs text-zinc-500">Login və parol şifrələnmiş saxlanılır və heç yerdə göstərilmir.</p>
            <div class="flex flex-wrap gap-2">
                <button class="btn-primary">Yadda saxla</button>
                <button type="submit" form="test-connection" class="btn-secondary">Bağlantını yoxla</button>
            </div>
        </form>
        <form id="test-connection" method="POST" action="{{ route('settings.online-payment.test') }}">@csrf</form>

        <div class="card card-pad space-y-3 text-sm text-zinc-600">
            <p class="font-extrabold text-ink">Necə işləyir?</p>
            <ol class="list-decimal space-y-1.5 pl-5">
                <li>Hər masanın <b>ödəniş QR-ını</b> çap edib masaya qoyun: <a href="{{ route('settings.tables.index') }}" class="font-semibold text-brand-700 underline">Masalar</a> → <x-icon name="card" class="inline size-4" />.</li>
                <li>Kassir POS-da masanı açıb <b>"Onlayn ödənişə göndər"</b> basır.</li>
                <li>Müştəri QR-ı skan edir, məbləği görür və Kapital Bank-ın səhifəsində kartla ödəyir.</li>
                <li>Ödəniş bankda təsdiqlənən kimi hesab avtomatik bağlanır (ödəniş növü — "Onlayn").</li>
            </ol>
        </div>
    </div>

    <div class="card mt-4 overflow-x-auto">
        <p class="card-pad pb-0 font-extrabold">Son onlayn ödənişlər</p>
        <table class="table-base">
            <thead><tr><th>Vaxt</th><th>Masa</th><th class="text-right">Məbləğ</th><th>Status</th><th>Bank</th><th>Çek</th><th>Göndərən</th><th>Qeyd</th></tr></thead>
            <tbody>
                @forelse ($payments as $p)
                    <tr>
                        <td class="whitespace-nowrap">{{ $p->created_at->format('d.m H:i') }}</td>
                        <td>{{ $p->table?->title ?? '—' }}</td>
                        <td class="text-right font-bold">{{ money($p->amount) }}</td>
                        <td>
                            <span @class(['badge-green' => $p->status === 'paid', 'badge-amber' => $p->status === 'pending', 'badge-gray' => $p->status === 'cancelled'])>{{ ['pending' => 'Gözlənilir', 'paid' => 'Ödənilib', 'cancelled' => 'Ləğv'][$p->status] ?? $p->status }}</span>
                            @if ($p->is_test)<span class="badge-gray">test</span>@endif
                        </td>
                        <td class="text-xs text-zinc-500">{{ $p->bank_order_id ? '#'.$p->bank_order_id.' · '.$p->bank_status : '—' }}</td>
                        <td>{{ $p->invoice?->number ?? '—' }}</td>
                        <td>{{ $p->requester?->name ?? '—' }}</td>
                        <td class="text-xs {{ str_contains((string) $p->note, 'qaytarın') || str_contains((string) $p->note, 'yoxlayın') ? 'font-bold text-red-700' : 'text-zinc-500' }}">{{ $p->note }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-zinc-500">Hələ onlayn ödəniş yoxdur.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
