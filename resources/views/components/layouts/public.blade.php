@props(['title' => null, 'description' => null])
@php($s = site())
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }} — restoran və kafe üçün POS sistemi</title>
    <meta name="description" content="{{ $description ?? 'AlfaPOS — bulud əsaslı POS: sifariş, mətbəx ekranı, QR menyu, anbar və hesabatlar. '.$s['company_name'].' şirkətinin məhsulu.' }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-canvas font-sans text-ink antialiased" x-data="{ menu: false }">
<header class="sticky top-0 z-30 border-b border-zinc-200/70 bg-canvas/90 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center gap-6 px-4 py-4 sm:px-6">
        <a href="{{ route('home') }}"><x-logo class="text-2xl" /></a>
        <nav class="hidden items-center gap-1 text-sm font-semibold text-zinc-600 md:flex">
            <a href="{{ route('home') }}#imkanlar" class="rounded-lg px-3 py-2 hover:bg-white">İmkanlar</a>
            <a href="{{ route('home') }}#qiymetler" class="rounded-lg px-3 py-2 hover:bg-white">Qiymətlər</a>
            <a href="{{ route('faq') }}" @class(['rounded-lg px-3 py-2 hover:bg-white', 'bg-white text-ink' => request()->routeIs('faq')])>Sual-cavab</a>
            <a href="{{ route('about') }}" @class(['rounded-lg px-3 py-2 hover:bg-white', 'bg-white text-ink' => request()->routeIs('about')])>Haqqımızda</a>
            <a href="{{ route('contact') }}" @class(['rounded-lg px-3 py-2 hover:bg-white', 'bg-white text-ink' => request()->routeIs('contact')])>Əlaqə</a>
        </nav>
        <div class="ml-auto flex items-center gap-2">
            @auth
                <a href="{{ auth()->user()->homeRoute() }}" class="btn-primary">Panelə keç</a>
            @else
                <a href="{{ route('login') }}" class="btn-ghost hidden sm:inline-flex">Giriş</a>
                <a href="{{ route('register') }}" class="btn-primary">Pulsuz başla</a>
            @endauth
            <button class="btn-ghost md:hidden" @click="menu = !menu" aria-label="Menyu"><x-icon name="menu" /></button>
        </div>
    </div>
    <nav x-cloak x-show="menu" class="border-t border-zinc-200 px-4 py-2 md:hidden">
        @foreach ([[route('home').'#imkanlar', 'İmkanlar'], [route('home').'#qiymetler', 'Qiymətlər'], [route('faq'), 'Sual-cavab'], [route('about'), 'Haqqımızda'], [route('contact'), 'Əlaqə'], [route('login'), 'Giriş']] as [$href, $label])
            <a href="{{ $href }}" class="block rounded-lg px-3 py-2.5 font-semibold text-zinc-700">{{ $label }}</a>
        @endforeach
    </nav>
</header>

<main class="flex-1">{{ $slot }}</main>

<footer class="border-t border-zinc-200 bg-white">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
        <div>
            <x-logo class="text-2xl" />
            <p class="mt-3 text-sm text-zinc-600">Restoran, kafe, bar və otellər üçün bulud əsaslı POS sistemi.</p>
            <p class="mt-3 text-sm text-zinc-500">AlfaPOS <b>{{ $s['company_name'] }}</b> şirkətinin məhsuludur.</p>
        </div>
        <div>
            <p class="font-extrabold">Məhsul</p>
            <ul class="mt-3 space-y-2 text-sm text-zinc-600">
                <li><a href="{{ route('home') }}#imkanlar" class="hover:text-brand-700">İmkanlar</a></li>
                <li><a href="{{ route('home') }}#qiymetler" class="hover:text-brand-700">Qiymətlər</a></li>
                <li><a href="{{ route('faq') }}" class="hover:text-brand-700">Sual-cavab</a></li>
                <li><a href="{{ route('register') }}" class="hover:text-brand-700">Pulsuz sınaq</a></li>
            </ul>
        </div>
        <div>
            <p class="font-extrabold">Şirkət</p>
            <ul class="mt-3 space-y-2 text-sm text-zinc-600">
                <li><a href="{{ route('about') }}" class="hover:text-brand-700">Haqqımızda</a></li>
                <li><a href="{{ route('contact') }}" class="hover:text-brand-700">Əlaqə</a></li>
                <li><a href="{{ route('privacy') }}" class="hover:text-brand-700">Məxfilik siyasəti</a></li>
                <li><a href="{{ route('terms') }}" class="hover:text-brand-700">İstifadə şərtləri</a></li>
            </ul>
        </div>
        <div>
            <p class="font-extrabold">Dəstək</p>
            <ul class="mt-3 space-y-2 text-sm text-zinc-600">
                <li><a href="tel:{{ $s['phone_intl'] }}" class="flex items-center gap-2 hover:text-brand-700"><x-icon name="online" class="size-4" /> {{ $s['phone'] }}</a></li>
                @if ($s['city_phone'])<li><a href="tel:{{ $s['city_phone_intl'] }}" class="flex items-center gap-2 hover:text-brand-700"><x-icon name="online" class="size-4" /> {{ $s['city_phone'] }}</a></li>@endif
                <li><a href="mailto:{{ $s['email'] }}" class="flex items-center gap-2 hover:text-brand-700"><x-icon name="file" class="size-4" /> {{ $s['email'] }}</a></li>
                @if ($s['whatsapp'])<li><a href="https://wa.me/{{ ltrim($s['whatsapp_intl'], '+') }}" target="_blank" rel="noopener" class="flex items-center gap-2 hover:text-brand-700"><x-icon name="note" class="size-4" /> WhatsApp</a></li>@endif
                @if ($s['address'])<li><a href="{{ $s['map_url'] ?: route('contact') }}" target="_blank" rel="noopener" class="flex items-start gap-2 hover:text-brand-700"><x-icon name="store" class="mt-0.5 size-4 shrink-0" /> {{ $s['address'] }}</a></li>@endif
            </ul>
        </div>
    </div>
    <div class="border-t border-zinc-100">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-5 text-xs text-zinc-500 sm:px-6">
            <p>© {{ date('Y') }} {{ $s['company_name'] }} · VÖEN {{ $s['voen'] }}. Bütün hüquqlar qorunur.</p>
            <p>AlfaPOS v{{ config('app.version') }}</p>
        </div>
    </div>
</footer>
</body>
</html>
