<x-layouts.app title="Sayt">
<div x-data="{ edit: null }">
    <x-page-header title="Sayt" subtitle="alfapos.app saytının əlaqə məlumatları, səhifələri və sual-cavabı. Dəyişikliklər dərhal saytda görünür.">
        <a href="{{ route('home') }}" target="_blank" class="btn-secondary"><x-icon name="arrow-right" class="size-4" /> Sayta bax</a>
    </x-page-header>

    <div class="mb-5 flex gap-1 rounded-xl bg-zinc-100 p-1 sm:inline-flex">
        @foreach (['contacts' => 'Əlaqə və rekvizitlər', 'pages' => 'Səhifələr', 'faq' => 'Sual-cavab'] as $key => $label)
            <a href="{{ route('admin.site.index', ['tab' => $key]) }}" @class(['rounded-lg px-4 py-2 text-sm font-bold', 'bg-white shadow-sm' => $tab === $key, 'text-zinc-500' => $tab !== $key])>{{ $label }}</a>
        @endforeach
    </div>

    @if ($tab === 'contacts')
        <form method="POST" action="{{ route('admin.site.settings') }}" class="grid gap-4 xl:grid-cols-3">
            @csrf @method('PUT')
            <div class="card card-pad grid gap-4 sm:grid-cols-2 xl:col-span-2">
                @foreach (\App\Models\SiteSetting::FIELDS as $key => [$label])
                    <div @class(['sm:col-span-2' => in_array($key, ['address', 'map_url'])])>
                        <label class="label" for="f-{{ $key }}">{{ $label }}</label>
                        <input id="f-{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key] ?? '') }}" class="input" @if (in_array($key, ['company_name', 'phone', 'email'])) required @endif>
                        @error($key)<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div class="sm:col-span-2"><button class="btn-primary">Yadda saxla</button></div>
            </div>
            <div class="space-y-4">
                <div class="card card-pad text-sm text-zinc-600">
                    <p class="mb-2 font-extrabold text-ink">Harada göstərilir?</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>Saytın footer-i, Əlaqə və Haqqımızda səhifələri</li>
                        <li>Hüquqi səhifələr: mətndəki <code>{telefon}</code>, <code>{şəhər_telefonu}</code>, <code>{email}</code>, <code>{voen}</code>, <code>{ünvan}</code>, <code>{şirkət}</code> yer tutucuları</li>
                        <li>POS ekran qoruyucusu, panelin menyusu, giriş səhifəsi (şirkət və mobil telefon)</li>
                    </ul>
                </div>
                <div class="card card-pad text-sm text-zinc-600">
                    <p class="mb-2 font-extrabold text-ink">Xəritə</p>
                    <p>"Xəritə linki" — "Xəritədə aç" düyməsi üçün (Google Maps paylaşım linki). Enlik və uzunluq — Əlaqə səhifəsindəki xəritə üçün; Google Maps-də nöqtəyə sağ klik → koordinatları kopyalayın.</p>
                    @if ($values['map_lat'] && $values['map_lng'])
                        <iframe class="mt-3 h-48 w-full rounded-xl" loading="lazy" src="https://maps.google.com/maps?q={{ urlencode($values['map_lat'].','.$values['map_lng']) }}&z=16&hl=az&output=embed" title="Xəritə"></iframe>
                    @endif
                </div>
            </div>
        </form>
    @endif

    @if ($tab === 'pages')
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($pages as $page)
                <div class="card card-pad flex flex-col">
                    <p class="text-xs font-bold tracking-wide text-zinc-400 uppercase">{{ \App\Models\SitePage::SLUGS[$page->slug] ?? $page->slug }}</p>
                    <p class="mt-1 text-lg font-extrabold">{{ $page->title }}</p>
                    <p class="mt-1 text-sm text-zinc-500">Yenilənib: {{ $page->updated_at->format('d.m.Y H:i') }} · {{ mb_strlen($page->body) }} simvol</p>
                    <div class="mt-4 flex gap-2">
                        <a href="{{ route('admin.site.pages.edit', $page->slug) }}" class="btn-primary btn-sm"><x-icon name="edit" class="size-4" /> Redaktə et</a>
                        <a href="{{ route(['about' => 'about', 'privacy' => 'privacy', 'terms' => 'terms'][$page->slug]) }}" target="_blank" class="btn-secondary btn-sm">Bax</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($tab === 'faq')
        <div class="mb-3 flex justify-end">
            <button class="btn-primary" @click="edit = { id: null, group: @js($groups->first() ?? 'Ümumi'), question: '', answer: '', is_active: true }"><x-icon name="plus" class="size-4" /> Yeni sual</button>
        </div>
        <div class="card overflow-x-auto">
            <table class="table-base">
                <thead><tr><th class="w-20">Sıra</th><th>Bölmə</th><th>Sual</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($faqs as $faq)
                    <tr>
                        <td class="whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.site.faqs.move', $faq) }}" class="inline">@csrf<input type="hidden" name="dir" value="up"><button class="btn-ghost btn-sm" title="Yuxarı" @disabled($loop->first)>↑</button></form>
                            <form method="POST" action="{{ route('admin.site.faqs.move', $faq) }}" class="inline">@csrf<input type="hidden" name="dir" value="down"><button class="btn-ghost btn-sm" title="Aşağı" @disabled($loop->last)>↓</button></form>
                        </td>
                        <td class="text-zinc-500">{{ $faq->group }}</td>
                        <td class="font-semibold">{{ $faq->question }}<p class="line-clamp-1 text-xs font-normal text-zinc-500">{{ $faq->answer }}</p></td>
                        <td>{!! $faq->is_active ? '<span class="badge-green">Görünür</span>' : '<span class="badge-gray">Gizli</span>' !!}</td>
                        <td class="text-right whitespace-nowrap">
                            <button class="btn-ghost btn-sm" @click="edit = @js($faq->only(['id', 'group', 'question', 'answer', 'is_active']))"><x-icon name="edit" class="size-4" /></button>
                            <form method="POST" action="{{ route('admin.site.faqs.destroy', $faq) }}" class="inline" onsubmit="return confirm('Sual silinsin?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty icon="note" title="Sual yoxdur" /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-sm text-zinc-500">Cavablarda <code>{telefon}</code>, <code>{şəhər_telefonu}</code>, <code>{email}</code>, <code>{şirkət}</code> yazsanız, Əlaqə ayarlarındakı dəyərlərlə əvəz olunur.</p>

        <datalist id="faq-groups">@foreach ($groups as $g)<option value="{{ $g }}">@endforeach</datalist>
        <x-modal show="edit" close="edit = null" title="Sual" width="max-w-2xl">
            <template x-if="edit"><form method="POST" :action="edit.id ? @js(url('admin/site/faqs')) + '/' + edit.id : @js(route('admin.site.faqs.store'))" id="faq-form" class="space-y-3">
                @csrf
                <template x-if="edit.id"><input type="hidden" name="_method" value="PUT"></template>
                <div><label class="label">Bölmə *</label><input name="group" x-model="edit.group" list="faq-groups" class="input" required maxlength="60"></div>
                <div><label class="label">Sual *</label><input name="question" x-model="edit.question" class="input" required maxlength="255"></div>
                <div><label class="label">Cavab *</label><textarea name="answer" x-model="edit.answer" rows="6" class="input" required maxlength="5000"></textarea></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" x-model="edit.is_active" class="rounded text-brand-500"> Saytda görünsün</label>
            </form></template>
            <x-slot:footer><button class="btn-secondary" @click="edit = null">Bağla</button><button class="btn-primary" form="faq-form">Saxla</button></x-slot:footer>
        </x-modal>
    @endif
</div>
</x-layouts.app>
