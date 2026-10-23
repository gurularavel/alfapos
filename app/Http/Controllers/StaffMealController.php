<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\StaffMeal;
use App\Models\User;
use App\Services\StockService;
use App\Support\PosTerminal;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Personal yeməyi: işçilərə verilən yeməklər hazır stokdan / anbardan silinir və dəyəri uçota düşür. */
class StaffMealController extends Controller
{
    public function index(Request $request, Tenancy $tenancy, PosTerminal $terminal): View
    {
        $from = query_date($request->query('from'), today()->toDateString())->startOfDay();
        $to = query_date($request->query('to'), today()->toDateString())->endOfDay();

        $meals = $terminal->scopeToBranch(StaffMeal::with(['items', 'creator:id,name']))
            ->whereBetween('created_at', [$from, $to])->latest('id')->get();
        $active = $meals->where('status', 'active');

        return view('staff-meals.index', [
            'meals' => $meals,
            'from' => $from,
            'to' => $to,
            'stats' => (object) [
                'meals' => $active->count(),
                'portions' => (int) $active->sum(fn ($m) => $m->items->sum('quantity')),
                'value' => (float) $active->sum('total'),
                'cost' => (float) $active->sum('cost'),
                'costUnknown' => $active->whereNull('cost')->count(),
                'people' => $active->pluck('employee_name')->unique()->count(),
            ],
            'byEmployee' => $active->groupBy('employee_name')->map(fn ($g, $name) => (object) [
                'name' => $name,
                'meals' => $g->count(),
                'value' => (float) $g->sum('total'),
                'cost' => (float) $g->sum('cost'),
            ])->sortByDesc('value')->values(),
            'employees' => User::where('tenant_id', $tenancy->id())->where('is_active', true)->orderBy('name')->get(['id', 'name', 'role', 'designation']),
            'menu' => MenuItem::where('is_enabled', true)->orderBy('title')->get(['id', 'title', 'price', 'category_id', 'track_stock', 'stock_quantity'])
                ->map(fn (MenuItem $m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'price' => (float) $m->price,
                    'category_id' => $m->category_id,
                    'stock' => $m->track_stock ? (float) $m->stock_quantity : null,
                ]),
            'categories' => Category::where('is_enabled', true)->orderBy('sort')->get(['id', 'title']),
        ]);
    }

    public function store(Request $request, Tenancy $tenancy, StockService $stock, PosTerminal $terminal): RedirectResponse
    {
        $tenantRule = fn (string $table) => Rule::exists($table, 'id')->where('tenant_id', $tenancy->id());
        $data = $request->validate([
            'user_id' => ['nullable', $tenantRule('users')],
            'employee_name' => ['required_without:user_id', 'nullable', 'string', 'max:120'],
            'meal_type' => ['required', Rule::in(array_keys(StaffMeal::MEAL_TYPES))],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', $tenantRule('menu_items')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ], ['items.required' => 'Ən azı bir yemək seçin.', 'employee_name.required_without' => 'İşçini seçin və ya adını yazın.']);

        $meal = DB::transaction(function () use ($data, $stock, $terminal) {
            $lines = collect($data['items'])->map(fn ($l) => (object) ['menu_item_id' => (int) $l['menu_item_id'], 'quantity' => (int) $l['quantity']]);
            $stock->assertAvailable($lines, 'items');

            $menu = MenuItem::whereIn('id', $lines->pluck('menu_item_id'))->get()->keyBy('id');
            $name = isset($data['user_id']) ? User::whereKey($data['user_id'])->value('name') : trim($data['employee_name']);
            $meal = StaffMeal::create([
                'branch_id' => $terminal->device()?->branch_id,
                'user_id' => $data['user_id'] ?? null,
                'employee_name' => $name,
                'meal_type' => $data['meal_type'],
                'note' => $data['note'] ?? null,
                'total' => $lines->sum(fn ($l) => $menu[$l->menu_item_id]->price * $l->quantity),
                'created_by' => $terminal->effectiveId(),
            ]);
            $meal->items()->createMany($lines->map(fn ($l) => [
                'menu_item_id' => $l->menu_item_id,
                'title' => $menu[$l->menu_item_id]->title,
                'quantity' => $l->quantity,
                'price' => $menu[$l->menu_item_id]->price,
            ])->all());

            $costs = $stock->consume($meal->items, -1, 'staff', ['staff_meal_id' => $meal->id, 'note' => "Personal yeməyi: {$name}"]);
            // Hər yeməyin mayası bilinirsə, qeydin mayası; biri bilinmirsə — null (natamam rəqəm göstərilmir)
            $meal->update(['cost' => in_array(null, $costs, true) ? null : round($meal->items->sum(fn ($i, $k) => $costs[$k] * $i->quantity), 2)]);
            AuditLog::record('staff_meal.created', $meal, ['employee' => $name, 'total' => $meal->total]);

            return $meal;
        });

        return back()->with('success', "{$meal->employee_name}: ".StaffMeal::MEAL_TYPES[$meal->meal_type].' qeydə alındı.');
    }

    /** Səhv qeydi ləğv edir: porsiyalar / xammal stoka qaytarılır. */
    public function cancel(StaffMeal $staffMeal, StockService $stock): RedirectResponse
    {
        abort_if($staffMeal->status === 'cancelled', 422, 'Artıq ləğv edilib.');

        DB::transaction(function () use ($staffMeal, $stock) {
            $staffMeal->update(['status' => 'cancelled']);
            $stock->consume($staffMeal->items, 1, 'staff', ['staff_meal_id' => $staffMeal->id, 'note' => "Ləğv: personal yeməyi — {$staffMeal->employee_name}"]);
            AuditLog::record('staff_meal.cancelled', $staffMeal);
        });

        return back()->with('success', 'Ləğv edildi, stok geri qaytarıldı.');
    }
}
