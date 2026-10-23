{{-- Müştərinin telefonunda açılan onlayn ödəniş səhifələri (masanın ödəniş QR-ı, bankdan qayıdış) --}}
@props(['tenant', 'table' => null, 'title' => 'Hesabın ödənişi'])
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $tenant->name }} — {{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas font-sans text-ink antialiased">
<div class="mx-auto flex min-h-screen max-w-xl flex-col bg-white">
    <header class="bg-brand-500 px-5 pt-8 pb-6 text-white">
        <div class="flex items-center gap-3">
            @if ($tenant->logo)<img src="{{ Storage::disk('public')->url($tenant->logo) }}" alt="" class="size-14 rounded-2xl bg-white object-cover">@endif
            <div>
                <h1 class="text-2xl font-black">{{ $tenant->name }}</h1>
                <p class="text-sm text-white/80">{{ $title }}</p>
            </div>
        </div>
        @if ($table)<p class="mt-4 inline-flex rounded-full bg-white/20 px-3 py-1 text-sm font-bold">{{ $table->label() }}</p>@endif
    </header>
    <main class="flex-1 p-5">{{ $slot }}</main>
    <footer class="px-5 pb-6 text-center text-xs text-zinc-400">Ödəniş Kapital Bank-ın təhlükəsiz səhifəsində aparılır. Kart məlumatlarınız bizə ötürülmür.</footer>
</div>
<x-cookie-banner position="top" />
</body>
</html>
