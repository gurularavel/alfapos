<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }} — Şəkil müəllifləri</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas font-sans text-ink antialiased">
<div class="mx-auto min-h-screen max-w-xl bg-white p-5">
    <a href="{{ route('qr.show', $tenant->qr_code) }}" class="btn-secondary btn-sm">← Menyu</a>
    <h1 class="mt-4 text-2xl font-black">Şəkil müəllifləri</h1>
    <p class="mt-1 text-sm text-zinc-500">Menyudakı bəzi şəkillər açıq lisenziyalarla (Creative Commons) istifadə olunur.</p>
    <ul class="mt-5 divide-y divide-zinc-100 text-sm">
        @forelse ($items as $item)
            <li class="py-3">
                <p class="font-bold">{{ $item->title }}</p>
                <p class="break-words text-zinc-600">
                    @foreach (explode(' · ', $item->image_credit) as $part)
                        @if (str_starts_with($part, 'http'))<a href="{{ $part }}" target="_blank" rel="noopener nofollow" class="text-brand-700 underline">mənbə</a>@else{{ $part }}@endif
                        @unless ($loop->last) · @endunless
                    @endforeach
                </p>
            </li>
        @empty
            <li class="py-3 text-zinc-500">Məlumat yoxdur.</li>
        @endforelse
    </ul>
</div>
</body>
</html>
