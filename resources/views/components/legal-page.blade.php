{{-- Hüquqi sənəd (SitePage, Markdown): başlıq, yenilənmə tarixi, "## " başlıqlarından mündəricat --}}
@props(['page'])
<section class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
    <p class="text-sm font-bold tracking-widest text-brand-600 uppercase">Hüquqi məlumat</p>
    <h1 class="mt-2 text-4xl font-black sm:text-5xl">{{ $page->title }}</h1>
    <p class="mt-3 text-sm text-zinc-500">Son yenilənmə: {{ $page->updated_at->translatedFormat('d F Y') }}</p>

    @if ($page->headings())
        <nav class="mt-8 rounded-2xl bg-white p-5 ring-1 ring-zinc-200">
            <p class="mb-2 text-sm font-bold text-zinc-500">Mündəricat</p>
            <ol class="grid list-decimal gap-1 pl-5 text-sm text-brand-700 sm:grid-cols-2">
                @foreach ($page->headings() as $i => $heading)
                    <li><a href="#b{{ $i + 1 }}" class="hover:underline">{{ $heading }}</a></li>
                @endforeach
            </ol>
        </nav>
    @endif

    <article class="md-content mt-8">{!! $page->html() !!}</article>
</section>
