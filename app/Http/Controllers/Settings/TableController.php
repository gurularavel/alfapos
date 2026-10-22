<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\StoreTable;
use App\Support\Tenancy;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function index(Tenancy $tenancy)
    {
        return view('settings.tables', [
            'tables' => StoreTable::with('branch:id,name')->orderBy('floor')->orderBy('title')->get(),
            'branches' => \App\Models\Branch::orderBy('id')->get(['id', 'name']),
            'tenant' => $tenancy->tenant(),
        ]);
    }

    public function store(Request $request, Tenancy $tenancy)
    {
        $limit = $tenancy->tenant()->plan?->max_tables;
        if ($limit && StoreTable::count() >= $limit) {
            return back()->withErrors(['title' => "Planınız maksimum {$limit} masaya icazə verir."]);
        }

        StoreTable::create($this->validated($request));

        return back()->with('success', 'Masa əlavə edildi.');
    }

    public function update(Request $request, StoreTable $table)
    {
        $table->update($this->validated($request));

        return back()->with('success', 'Masa yeniləndi.');
    }

    public function destroy(StoreTable $table)
    {
        $table->delete();

        return back()->with('success', 'Masa silindi.');
    }

    /** Zal planı redaktoru: masaları sürüşdürüb yerləşdirmək. */
    public function plan()
    {
        return view('settings.tables-plan', [
            'tables' => StoreTable::orderBy('floor')->orderByRaw('LENGTH(title)')->orderBy('title')->get()
                ->map(fn (StoreTable $t) => [
                    'id' => $t->id, 'title' => $t->title, 'floor' => $t->floor ?: 'Ümumi', 'seats' => $t->seating_capacity,
                    'deposit' => (float) $t->deposit, 'x' => $t->pos_x, 'y' => $t->pos_y,
                ]),
        ]);
    }

    public function savePlan(Request $request)
    {
        $data = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*.id' => ['required', 'integer'],
            'positions.*.x' => ['nullable', 'numeric', 'between:0,100'],
            'positions.*.y' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        // Yalnız bu biznesin masaları (global scope) yenilənir
        $tables = StoreTable::whereIn('id', collect($data['positions'])->pluck('id'))->get()->keyBy('id');
        foreach ($data['positions'] as $p) {
            $tables->get($p['id'])?->update(['pos_x' => $p['x'], 'pos_y' => $p['y']]);
        }

        return response()->json(['message' => 'Zal planı yadda saxlanıldı.']);
    }

    /** Masanın üstünə qoymaq üçün çap ediləcək QR kart. */
    public function qr(StoreTable $table, Tenancy $tenancy)
    {
        $tenant = $tenancy->tenant();

        return view('print.table-qr', [
            'table' => $table,
            'tenant' => $tenant,
            'url' => route('qr.show', ['code' => $tenant->qr_code, 't' => $table->qr_token]),
        ]);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:60'],
            'floor' => ['nullable', 'string', 'max:60'],
            'seating_capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'deposit' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'branch_id' => ['nullable', \Illuminate\Validation\Rule::exists('branches', 'id')->where('tenant_id', app(Tenancy::class)->id())],
        ]);

        return ['deposit' => (float) ($data['deposit'] ?? 0)] + $data;
    }
}
