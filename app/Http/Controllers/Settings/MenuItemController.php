<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\Tax;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MenuItemController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index(Request $request)
    {
        $items = MenuItem::with(['category:id,title', 'tax:id,title'])->withCount(['variants', 'addons'])
            ->when($request->query('q'), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category_id', $c))
            ->orderBy('title')->paginate(30)->withQueryString();

        return view('settings.menu-items.index', [
            'items' => $items,
            'categories' => Category::orderBy('sort')->get(['id', 'title']),
        ]);
    }

    public function create()
    {
        return $this->form(new MenuItem(['is_enabled' => true]));
    }

    public function edit(MenuItem $menuItem)
    {
        return $this->form($menuItem->load('variants', 'addons', 'recipeItems'));
    }

    public function store(Request $request)
    {
        $limit = $this->tenancy->tenant()->plan?->max_menu_items;
        if ($limit && MenuItem::count() >= $limit) {
            return back()->withErrors(['title' => "Planınız maksimum {$limit} menyu məhsuluna icazə verir."])->withInput();
        }

        $item = DB::transaction(fn () => $this->save($request, new MenuItem));

        return redirect()->route('settings.menu-items.edit', $item)->with('success', 'Məhsul əlavə edildi.');
    }

    public function update(Request $request, MenuItem $menuItem)
    {
        DB::transaction(fn () => $this->save($request, $menuItem));

        return back()->with('success', 'Məhsul yeniləndi.');
    }

    public function destroy(MenuItem $menuItem)
    {
        if ($menuItem->image) {
            Storage::disk('public')->delete($menuItem->image);
        }
        $menuItem->delete();

        return redirect()->route('settings.menu-items.index')->with('success', 'Məhsul silindi.');
    }

    protected function form(MenuItem $item)
    {
        return view('settings.menu-items.form', [
            'item' => $item,
            'categories' => Category::orderBy('sort')->get(['id', 'title']),
            'taxes' => Tax::orderBy('title')->get(),
            'inventory' => InventoryItem::orderBy('title')->get(['id', 'title', 'unit']),
        ]);
    }

    /**
     * Məhsul + variantlar + addonlar + resept bir formadan saxlanılır.
     * Variant/addon sətirləri id ilə gəlir: id varsa yenilənir, yoxdursa yaradılır, siyahıda olmayanlar silinir.
     */
    protected function save(Request $request, MenuItem $item): MenuItem
    {
        $tid = $this->tenancy->id();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('tenant_id', $tid)],
            'tax_id' => ['nullable', Rule::exists('taxes', 'id')->where('tenant_id', $tid)],
            'image' => ['nullable', 'image', 'max:2048'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.title' => ['required', 'string', 'max:60'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'addons' => ['nullable', 'array'],
            'addons.*.id' => ['nullable', 'integer'],
            'addons.*.title' => ['required', 'string', 'max:60'],
            'addons.*.price' => ['required', 'numeric', 'min:0'],
            'recipe' => ['nullable', 'array'],
            'recipe.*.inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('tenant_id', $tid)],
            'recipe.*.quantity' => ['required', 'numeric', 'gt:0'],
            'recipe.*.target' => ['nullable', 'string', 'max:40'], // "", "v:<idx>", "a:<idx>"
        ]);

        $item->fill(collect($data)->only(['title', 'description', 'price', 'category_id', 'tax_id'])->all());
        $item->is_enabled = $request->boolean('is_enabled');
        if ($request->boolean('remove_image') && $item->image) {
            Storage::disk('public')->delete($item->image);
            $item->image = null;
        }
        if ($request->hasFile('image')) {
            if ($item->image) {
                Storage::disk('public')->delete($item->image);
            }
            $item->image = $request->file('image')->store("tenants/{$tid}/menu", 'public');
        }
        $item->save();

        $variants = $this->syncChildren($item->variants(), $data['variants'] ?? []);
        $addons = $this->syncChildren($item->addons(), $data['addons'] ?? []);

        $item->recipeItems()->delete();
        foreach ($data['recipe'] ?? [] as $row) {
            [$kind, $idx] = array_pad(explode(':', (string) ($row['target'] ?? '')), 2, null);
            $item->recipeItems()->create([
                'inventory_item_id' => $row['inventory_item_id'],
                'quantity' => $row['quantity'],
                'variant_id' => $kind === 'v' ? ($variants[$idx] ?? null) : null,
                'addon_id' => $kind === 'a' ? ($addons[$idx] ?? null) : null,
            ]);
        }

        return $item;
    }

    /** @return array<int,int> form index => saxlanmış id */
    protected function syncChildren($relation, array $rows): array
    {
        $keep = [];
        $map = [];
        foreach (array_values($rows) as $i => $row) {
            $model = ! empty($row['id']) ? (clone $relation)->find($row['id']) : null;
            $model ? $model->update(['title' => $row['title'], 'price' => $row['price']])
                : $model = $relation->create(['title' => $row['title'], 'price' => $row['price']]);
            $keep[] = $model->id;
            $map[$i] = $model->id;
        }
        (clone $relation)->whereNotIn('id', $keep)->delete();

        return $map;
    }
}
