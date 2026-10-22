@props(['title' => null])
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas font-sans text-ink antialiased">
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="relative hidden overflow-hidden bg-brand-50 lg:flex lg:flex-col lg:justify-center lg:px-16">
        <div class="absolute -right-40 -bottom-40 size-[520px] rounded-full bg-brand-400/80"></div>
        <a href="{{ route('home') }}" class="relative mb-10"><x-logo class="text-3xl" /></a>
        <h2 class="relative text-5xl leading-tight font-black">Kafe. Restoran.<br><span class="text-transparent [-webkit-text-stroke:1.5px_var(--color-ink)]">Otel. Bar.</span></h2>
        <p class="relative mt-6 max-w-md text-lg text-zinc-600">Sifariş, mətbəx, anbar və hesabatlar — hamısı bir yerdə, istənilən cihazdan.</p>
    </div>
    <div class="flex items-center justify-center p-6">
        <div class="w-full max-w-md">
            <a href="{{ route('home') }}" class="mb-8 block lg:hidden"><x-logo class="text-3xl" /></a>
            {{ $slot }}
        </div>
    </div>
</div>
</body>
</html>
