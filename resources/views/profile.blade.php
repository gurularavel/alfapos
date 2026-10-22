<x-layouts.app title="Profil">
    <x-page-header title="Profil" />
    <form method="POST" action="{{ route('profile.update') }}" class="card card-pad max-w-2xl space-y-4">
        @csrf @method('PUT')
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Ad</label><input name="name" value="{{ old('name', $user->name) }}" class="input" required></div>
            <div><label class="label">Telefon</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="input"></div>
            <div class="sm:col-span-2"><label class="label">Email</label><input name="email" type="email" value="{{ old('email', $user->email) }}" class="input" required></div>
        </div>
        <hr class="border-zinc-100">
        <p class="font-bold">Parolu dəyiş</p>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label class="label">Cari parol</label><input name="current_password" type="password" class="input" autocomplete="current-password"></div>
            <div><label class="label">Yeni parol</label><input name="password" type="password" class="input" autocomplete="new-password"></div>
            <div><label class="label">Təkrar</label><input name="password_confirmation" type="password" class="input" autocomplete="new-password"></div>
        </div>
        <button class="btn-primary">Saxla</button>
    </form>
</x-layouts.app>
