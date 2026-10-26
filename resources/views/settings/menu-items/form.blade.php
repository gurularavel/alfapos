<x-layouts.app :title="$item->exists ? $item->title : 'Yeni məhsul'">
@php
    $uid = 0;
    $variants = old('variants') ? array_values(old('variants')) : $item->variants->map(fn ($v) => ['id' => $v->id, 'title' => $v->title, 'price' => (float) $v->price])->all();
    $addons = old('addons') ? array_values(old('addons')) : $item->addons->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'price' => (float) $a->price])->all();
    foreach ($variants as &$v) { $v['uid'] = 'v'.(++$uid); } unset($v);
    foreach ($addons as &$a) { $a['uid'] = 'a'.(++$uid); } unset($a);
    $recipe = $item->recipeItems->map(fn ($r) => [
        'inventory_item_id' => $r->inventory_item_id,
        'quantity' => qty($r->quantity),
        'target' => $r->variant_id ? collect($variants)->firstWhere('id', $r->variant_id)['uid'] ?? '' : ($r->addon_id ? collect($addons)->firstWhere('id', $r->addon_id)['uid'] ?? '' : ''),
    ])->all();
@endphp
<div x-data="{
        variants: @js($variants), addons: @js($addons), recipe: @js($recipe), uid: {{ $uid }},
        inventory: @js($inventory->keyBy('id')),
        add(list) { this[list].push({ id: null, uid: list[0] + (++this.uid), title: '', price: 0 }) },
        targetIndex(t) {
            if (!t) return '';
            const list = t[0] === 'v' ? this.variants : this.addons;
            const i = list.findIndex(x => x.uid === t);
            return i < 0 ? '' : t[0] + ':' + i;
        },
        price: @js((float) old('price', $item->price)),
        // Əsas reseptin (variant/əlavəsiz) 1 porsiyasının mayası; qiyməti yazılmamış xammal varsa null
        get recipeCost() {
            const base = this.recipe.filter(r => !r.target && r.inventory_item_id && r.quantity);
            if (!base.length || base.some(r => this.inventory[r.inventory_item_id]?.cost_price == null)) return null;
            return base.reduce((s, r) => s + r.quantity * this.inventory[r.inventory_item_id].cost_price, 0);
        },
        get unpriced() {
            return this.recipe.filter(r => !r.target && r.inventory_item_id && this.inventory[r.inventory_item_id]?.cost_price == null).map(r => this.inventory[r.inventory_item_id].title);
        },
     }">
    <x-page-header :title="$item->exists ? $item->title : 'Yeni məhsul'">
        <a href="{{ route('settings.menu-items.index') }}" class="btn-secondary">← Menyu</a>
        @if ($item->exists)
            <form method="POST" action="{{ route('settings.menu-items.destroy', $item) }}" onsubmit="return confirm('Məhsul silinsin?')">@csrf @method('DELETE')<button class="btn-ghost text-red-600"><x-icon name="trash" class="size-4" /> Sil</button></form>
        @endif
    </x-page-header>

    <form method="POST" action="{{ $item->exists ? route('settings.menu-items.update', $item) : route('settings.menu-items.store') }}" enctype="multipart/form-data" class="grid gap-4 xl:grid-cols-3">
        @csrf
        @if ($item->exists) @method('PUT') @endif

        <div class="space-y-4 xl:col-span-2">
            <div class="card card-pad grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="label">Ad *</label><input name="title" value="{{ old('title', $item->title) }}" class="input" required></div>
                <div class="sm:col-span-2"><label class="label">Təsvir</label><textarea name="description" rows="2" class="input">{{ old('description', $item->description) }}</textarea></div>
                <div><label class="label">Əsas qiymət *</label><input type="number" step="0.01" min="0" name="price" x-model.number="price" class="input" required></div>
                <div><label class="label">Kateqoriya</label>
                    <select name="category_id" class="input"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $item->category_id) == $c->id)>{{ $c->title }}</option>@endforeach</select>
                </div>
                <div><label class="label">Şöbə (çek hara çıxsın)</label>
                    <select name="department_id" class="input"><option value="">Kateqoriyaya görə</option>@foreach ($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id', $item->department_id) == $d->id)>{{ $d->name }}</option>@endforeach</select>
                </div>
                <div><label class="label">Vergi</label>
                    <select name="tax_id" class="input"><option value="">Vergisiz</option>@foreach ($taxes as $t)<option value="{{ $t->id }}" @selected(old('tax_id', $item->tax_id) == $t->id)>{{ $t->title }} ({{ $t->type === 'inclusive' ? 'daxil' : 'üstə' }})</option>@endforeach</select>
                </div>
                <label class="mt-7 flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $item->is_enabled)) class="rounded text-brand-500"> Satışda (POS və QR menyuda görünür)</label>
            </div>

            @foreach (['variants' => ['Variantlar', 'Ölçü və ya növ (Kiçik / Orta / Böyük). Variant seçiləndə əsas qiymət əvəzinə variant qiyməti tətbiq olunur.'], 'addons' => ['Əlavələr', 'İstəyə bağlı əlavələr (əlavə pendir, sirop). Qiyməti üstə gəlir.']] as $list => [$heading, $hint])
                <div class="card card-pad">
                    <div class="mb-3 flex items-center justify-between">
                        <div><p class="font-extrabold">{{ $heading }}</p><p class="text-xs text-zinc-500">{{ $hint }}</p></div>
                        <button type="button" class="btn-secondary btn-sm" @click="add('{{ $list }}')"><x-icon name="plus" class="size-4" /> Əlavə et</button>
                    </div>
                    <template x-for="(row, i) in {{ $list }}" :key="row.uid">
                        <div class="mb-2 grid grid-cols-[1fr_8rem_auto] gap-2">
                            <input type="hidden" :name="'{{ $list }}[' + i + '][id]'" :value="row.id">
                            <input :name="'{{ $list }}[' + i + '][title]'" x-model="row.title" class="input" placeholder="Ad" required>
                            <input type="number" step="0.01" min="0" :name="'{{ $list }}[' + i + '][price]'" x-model="row.price" class="input" placeholder="Qiymət" required>
                            <button type="button" class="btn-ghost text-red-600" @click="{{ $list }}.splice(i, 1)"><x-icon name="trash" class="size-4" /></button>
                        </div>
                    </template>
                    <p x-show="!{{ $list }}.length" class="text-sm text-zinc-400">Yoxdur.</p>
                </div>
            @endforeach

            <div class="card card-pad">
                <div class="mb-3 flex items-center justify-between">
                    <div><p class="font-extrabold">Resept (anbar)</p><p class="text-xs text-zinc-500">Hər satışda bu inqrediyentlər anbardan avtomatik silinir. Variant/əlavə seçsəniz, yalnız o seçiləndə silinir.</p></div>
                    <button type="button" class="btn-secondary btn-sm" @click="recipe.push({ inventory_item_id: '', quantity: '', target: '' })" @disabled($inventory->isEmpty())><x-icon name="plus" class="size-4" /> İnqrediyent</button>
                </div>
                @if ($inventory->isEmpty())
                    <p class="text-sm text-zinc-500">Əvvəlcə <a href="{{ route('inventory.index') }}" class="text-brand-600 underline">Anbar</a> bölməsində stok məhsulları yaradın.</p>
                @endif
                <div class="mb-3 flex flex-wrap gap-x-6 gap-y-1 rounded-xl bg-zinc-50 px-3 py-2 text-sm" x-show="recipe.some(r => !r.target && r.inventory_item_id)">
                    <span>Maya dəyəri (1 porsiya): <b x-text="recipeCost !== null ? money(recipeCost) : '—'"></b></span>
                    <span x-show="recipeCost !== null && price > 0">Marja: <b :class="(price - recipeCost) / price < 0.3 ? 'text-red-600' : 'text-brand-700'" x-text="money(price - recipeCost) + ' · ' + Math.round((price - recipeCost) / price * 100) + '%'"></b></span>
                    <span x-show="unpriced.length" class="text-amber-600">Alış qiyməti yazılmayıb: <span x-text="unpriced.join(', ')"></span></span>
                </div>
                <template x-for="(r, i) in recipe" :key="i">
                    <div class="mb-2 grid gap-2 sm:grid-cols-[1fr_8rem_12rem_auto]">
                        <select :name="'recipe[' + i + '][inventory_item_id]'" x-model="r.inventory_item_id" class="input" required>
                            <option value="">İnqrediyent seçin</option>
                            @foreach ($inventory as $inv)<option value="{{ $inv->id }}">{{ $inv->title }}</option>@endforeach
                        </select>
                        <div class="relative">
                            <input type="number" step="0.0001" min="0.0001" :name="'recipe[' + i + '][quantity]'" x-model="r.quantity" class="input pr-10" placeholder="Miqdar" required>
                            <span class="absolute top-1/2 right-3 -translate-y-1/2 text-xs text-zinc-400" x-text="inventory[r.inventory_item_id]?.unit || ''"></span>
                        </div>
                        <input type="hidden" :name="'recipe[' + i + '][target]'" :value="targetIndex(r.target)">
                        <select x-model="r.target" class="input">
                            <option value="">Hər satışda</option>
                            <template x-for="v in variants" :key="v.uid"><option :value="v.uid" x-text="'Variant: ' + (v.title || '—')" :selected="r.target === v.uid"></option></template>
                            <template x-for="a in addons" :key="a.uid"><option :value="a.uid" x-text="'Əlavə: ' + (a.title || '—')" :selected="r.target === a.uid"></option></template>
                        </select>
                        <button type="button" class="btn-ghost text-red-600" @click="recipe.splice(i, 1)"><x-icon name="trash" class="size-4" /></button>
                    </div>
                </template>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card card-pad" x-data="{ preview: @js($item->imageUrl()) }">
                <p class="label">Şəkil</p>
                <div class="mb-3 flex aspect-[4/3] items-center justify-center overflow-hidden rounded-xl bg-brand-50">
                    <template x-if="preview"><img :src="preview" alt="" class="size-full object-cover"></template>
                    <template x-if="!preview"><x-icon name="orders" class="size-10 text-brand-300" /></template>
                </div>
                <input type="file" name="image" accept="image/*" class="text-sm" @change="preview = URL.createObjectURL($event.target.files[0])">
                @if ($item->image)<label class="mt-2 flex items-center gap-2 text-sm text-red-600"><input type="checkbox" name="remove_image" value="1" class="rounded"> Şəkli sil</label>@endif
                <label class="label mt-3">Şəkil müəllifi / lisenziya <span class="font-normal text-zinc-400">— istəyə bağlı</span></label>
                <input name="image_credit" value="{{ old('image_credit', $item->image_credit) }}" class="input text-xs" maxlength="500" placeholder="Məs. Ad Soyad · CC BY 4.0 · mənbə linki">
                <p class="mt-1 text-xs text-zinc-500">İnternetdən götürülmüş CC BY şəkillər üçün doldurun — QR menyuda "Şəkil müəllifləri" səhifəsində göstərilir.</p>
            </div>
            <button class="btn-primary w-full py-3">Yadda saxla</button>
        </div>
    </form>
</div>
</x-layouts.app>
