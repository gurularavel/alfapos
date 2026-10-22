@php($page = \App\Models\SitePage::bySlug('about'))
@php($s = site())
<x-layouts.public title="Haqqımızda" :description="\App\Models\SitePage::placeholders((string) $page->description)">
<section class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
    <p class="text-sm font-bold tracking-widest text-brand-600 uppercase">Haqqımızda</p>
    <h1 class="mt-2 text-4xl font-black sm:text-5xl">{{ $page->title }}</h1>
    <article class="md-content mt-6">{!! $page->html() !!}</article>

    <div class="mt-14 grid gap-4 rounded-3xl bg-white p-8 ring-1 ring-zinc-200 sm:grid-cols-2">
        <div>
            <p class="text-xl font-black">Rekvizitlər</p>
            <dl class="mt-3 space-y-1.5 text-sm text-zinc-600">
                <div><dt class="inline font-semibold text-ink">Şirkət:</dt> <dd class="inline">{{ $s['company_name'] }}</dd></div>
                @if ($s['voen'])<div><dt class="inline font-semibold text-ink">VÖEN:</dt> <dd class="inline">{{ $s['voen'] }}</dd></div>@endif
                @if ($s['address'])<div><dt class="inline font-semibold text-ink">Ünvan:</dt> <dd class="inline">{{ $s['address'] }}</dd></div>@endif
            </dl>
        </div>
        <div>
            <p class="text-xl font-black">Əlaqə</p>
            <p class="mt-3 space-y-1 text-sm text-zinc-600">
                <a href="tel:{{ $s['phone_intl'] }}" class="block font-bold text-brand-700">{{ $s['phone'] }}</a>
                @if ($s['city_phone'])<a href="tel:{{ $s['city_phone_intl'] }}" class="block font-bold text-brand-700">{{ $s['city_phone'] }}</a>@endif
                <a href="mailto:{{ $s['email'] }}" class="block font-bold text-brand-700">{{ $s['email'] }}</a>
            </p>
            <a href="{{ route('contact') }}" class="btn-primary mt-4">Əlaqə səhifəsi</a>
        </div>
    </div>
</section>
</x-layouts.public>
