<x-layouts.guest title="Giriş">
    <div class="card card-pad sm:p-8">
        <h1 class="text-2xl font-extrabold">Daxil olun</h1>
        <p class="mt-1 text-sm text-zinc-500">Biznes hesabınıza giriş edin.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" required autofocus autocomplete="username">
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="password">Parol</label>
                <input id="password" name="password" type="password" class="input" required autocomplete="current-password">
            </div>
            <label class="flex items-center gap-2 text-sm text-zinc-600">
                <input type="checkbox" name="remember" class="rounded border-zinc-300 text-brand-500"> Məni xatırla
            </label>
            <button class="btn-primary w-full py-3">Daxil ol</button>
        </form>

        <div class="my-5 flex items-center gap-3 text-xs text-zinc-400"><span class="h-px flex-1 bg-zinc-200"></span>və ya<span class="h-px flex-1 bg-zinc-200"></span></div>
        <a href="{{ route('register') }}" class="btn-secondary w-full py-3">Yeni biznes hesabı yarat</a>
    </div>
    @if (app()->isLocal())
        <p class="mt-4 text-center text-xs text-zinc-500">Demo: demo@pos.test · kassir@pos.test · metbex@pos.test · super@pos.test — parol: <code>password</code></p>
    @endif
</x-layouts.guest>
