<x-layouts.guest title="Qeydiyyat">
    <div class="card card-pad sm:p-8">
        <h1 class="text-2xl font-extrabold">Biznesinizi qeydiyyatdan keçirin</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ \App\Services\TenantProvisioner::TRIAL_DAYS }} gün pulsuz sınaq. Kart tələb olunmur.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="label" for="business_name">Müəssisənin adı</label>
                <input id="business_name" name="business_name" value="{{ old('business_name') }}" class="input" required autofocus placeholder="Məs. Nərgiz Kafe">
                @error('business_name')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="name">Adınız</label>
                    <input id="name" name="name" value="{{ old('name') }}" class="input" required>
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="phone">Telefon</label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" class="input" placeholder="+994">
                </div>
            </div>
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" required autocomplete="username">
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="password">Parol</label>
                    <input id="password" name="password" type="password" class="input" required autocomplete="new-password">
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="password_confirmation">Parol (təkrar)</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password">
                </div>
            </div>
            <p class="text-xs text-zinc-500">Hesab yaratmaqla <a href="{{ route('terms') }}" target="_blank" class="font-semibold text-brand-700 underline">İstifadə şərtləri</a> və <a href="{{ route('privacy') }}" target="_blank" class="font-semibold text-brand-700 underline">Məxfilik siyasəti</a> ilə razılaşırsınız.</p>
            <button class="btn-primary w-full py-3">Hesab yarat</button>
        </form>
        <p class="mt-5 text-center text-sm text-zinc-500">Hesabınız var? <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:underline">Daxil olun</a></p>
    </div>
</x-layouts.guest>
