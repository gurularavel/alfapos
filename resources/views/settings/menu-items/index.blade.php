<x-layouts.app title="Menyu">
    <x-page-header title="Menyu" subtitle="{{ $items->total() }} məhsul">
        <a href="{{ route('settings.categories.index') }}" class="btn-secondary"><x-icon name="tag" class="size-4" /> Kateqoriyalar</a>
        <a href="{{ route('settings.menu-items.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Yeni məhsul</a>
    </x-page-header>

    <form class="card mb-4 grid gap-2 p-3 sm:grid-cols-[1fr_14rem_auto]">
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Məhsul axtar">
        <select name="category" class="input"><option value="">Bütün kateqoriyalar</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->title }}</option>@endforeach</select>
        <button class="btn-primary"><x-icon name="search" class="size-4" /></button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th></th><th>Məhsul</th><th>Kateqoriya</th><th class="text-right">Qiymət</th><th>Vergi</th><th>Variant / əlavə</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($items as $item)
                <tr>
                    <td class="w-14">
                        <div class="flex size-10 items-center justify-center overflow-hidden rounded-lg bg-brand-50 font-black text-brand-300">
                            @if ($item->image)<img src="{{ $item->imageUrl() }}" alt="" class="size-full object-cover">@else{{ mb_substr($item->title, 0, 1) }}@endif
                        </div>
                    </td>
                    <td class="font-bold"><a href="{{ route('settings.menu-items.edit', $item) }}" class="hover:text-brand-600">{{ $item->title }}</a></td>
                    <td class="text-zinc-500">{{ $item->category?->title ?? '—' }}</td>
                    <td class="text-right font-semibold">{{ money($item->price) }}</td>
                    <td class="text-zinc-500">{{ $item->tax?->title ?? '—' }}</td>
                    <td class="text-zinc-500">{{ $item->variants_count }} / {{ $item->addons_count }}</td>
                    <td>{!! $item->is_enabled ? '<span class="badge-green">Aktiv</span>' : '<span class="badge-gray">Deaktiv</span>' !!}</td>
                    <td class="text-right"><a href="{{ route('settings.menu-items.edit', $item) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="size-4" /></a></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="orders" title="Menyu boşdur">İlk məhsulunuzu əlavə edin.</x-empty></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $items->links() }}</div>
</x-layouts.app>
