<x-layouts.app title="Kateqoriyalar">
<div x-data="{ edit: null }">
    <x-page-header title="Kateqoriyalar">
        <a href="{{ route('settings.index') }}" class="btn-secondary">← Ayarlar</a>
        <button class="btn-primary" @click="edit = { id: null, title: '', sort: {{ $categories->count() }}, is_enabled: true }"><x-icon name="plus" class="size-4" /> Yeni kateqoriya</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Sıra</th><th>Ad</th><th>Məhsul</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($categories as $c)
                <tr>
                    <td class="w-16 text-zinc-500">{{ $c->sort }}</td>
                    <td class="font-bold">{{ $c->title }}</td>
                    <td><a href="{{ route('settings.menu-items.index', ['category' => $c->id]) }}" class="text-brand-600 hover:underline">{{ $c->menu_items_count }}</a></td>
                    <td>{!! $c->is_enabled ? '<span class="badge-green">Görünür</span>' : '<span class="badge-gray">Gizli</span>' !!}</td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn-ghost btn-sm" @click="edit = @js($c->only(['id', 'title', 'sort', 'is_enabled']))"><x-icon name="edit" class="size-4" /></button>
                        <form method="POST" action="{{ route('settings.categories.destroy', $c) }}" class="inline" onsubmit="return confirm('Silinsin? Məhsullar kateqoriyasız qalacaq.')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty icon="tag" title="Kateqoriya yoxdur" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-modal show="edit" close="edit = null" title="Kateqoriya" width="max-w-md">
        <template x-if="edit"><form method="POST" :action="edit.id ? @js(url('dashboard/settings/categories')) + '/' + edit.id : @js(route('settings.categories.store'))" id="cat-form" class="grid gap-3 sm:grid-cols-[1fr_6rem]">
            @csrf
            <template x-if="edit.id"><input type="hidden" name="_method" value="PUT"></template>
            <div><label class="label">Ad *</label><input name="title" x-model="edit.title" class="input" required></div>
            <div><label class="label">Sıra</label><input type="number" min="0" name="sort" x-model="edit.sort" class="input"></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" x-model="edit.is_enabled" class="rounded text-brand-500"> POS və QR menyuda görünsün</label>
        </form></template>
        <x-slot:footer><button class="btn-secondary" @click="edit = null">Bağla</button><button class="btn-primary" form="cat-form">Saxla</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
