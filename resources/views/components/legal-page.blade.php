{{-- Hüquqi sənəd səhifəsi: başlıq, qüvvəyə minmə tarixi, mündəricat və bölmələr (sections = [[başlıq, html], ...]) --}}
@props(['title', 'updated', 'intro', 'sections'])
<section class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
    <p class="text-sm font-bold tracking-widest text-brand-600 uppercase">Hüquqi məlumat</p>
    <h1 class="mt-2 text-4xl font-black sm:text-5xl">{{ $title }}</h1>
    <p class="mt-3 text-sm text-zinc-500">Son yenilənmə: {{ $updated }}</p>
    <p class="mt-6 text-lg text-zinc-600">{!! $intro !!}</p>

    <nav class="mt-8 rounded-2xl bg-white p-5 ring-1 ring-zinc-200">
        <p class="mb-2 text-sm font-bold text-zinc-500">Mündəricat</p>
        <ol class="grid list-decimal gap-1 pl-5 text-sm text-brand-700 sm:grid-cols-2">
            @foreach ($sections as $i => [$heading])
                <li><a href="#b{{ $i + 1 }}" class="hover:underline">{{ $heading }}</a></li>
            @endforeach
        </ol>
    </nav>

    <div class="mt-10 space-y-10">
        @foreach ($sections as $i => [$heading, $body])
            <section id="b{{ $i + 1 }}" class="scroll-mt-24">
                <h2 class="text-xl font-black">{{ $i + 1 }}. {{ $heading }}</h2>
                <div class="mt-3 space-y-3 leading-relaxed text-zinc-700 [&_li]:ml-5 [&_li]:list-disc [&_ul]:space-y-1.5">{!! $body !!}</div>
            </section>
        @endforeach
    </div>
</section>
