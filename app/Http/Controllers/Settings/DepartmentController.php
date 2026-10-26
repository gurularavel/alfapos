<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Şöbələr (mətbəx, bar ...): sifariş göndəriləndə hər şöbəyə öz çeki; çap yeri — kassa və ya şöbənin öz cihazı. */
class DepartmentController extends Controller
{
    public function index()
    {
        return view('settings.departments', [
            'departments' => Department::orderBy('sort')->orderBy('id')->get(),
            'categories' => Category::orderBy('sort')->get(['id', 'title', 'department_id']),
        ]);
    }

    public function store(Request $request)
    {
        $this->save(new Department, $request);

        return back()->with('success', 'Şöbə əlavə edildi.');
    }

    public function update(Request $request, Department $department)
    {
        $this->save($department, $request);

        return back()->with('success', 'Şöbə yeniləndi.');
    }

    public function destroy(Department $department)
    {
        abort_if($department->is_default, 422, 'Əsas şöbəni silmək olmaz — əvvəlcə başqa şöbəni əsas edin.');
        // Kateqoriya və məhsullar əsas şöbəyə keçir (FK nullOnDelete)
        $department->delete();

        return back()->with('success', 'Şöbə silindi. Onun kateqoriya və məhsulları əsas şöbəyə keçdi.');
    }

    protected function save(Department $department, Request $request): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'print_at' => ['required', Rule::in(array_keys(Department::PRINT_AT))],
            'sort' => ['nullable', 'integer', 'min:0'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
        ]);

        DB::transaction(function () use ($department, $data, $request) {
            $department->fill(['name' => $data['name'], 'print_at' => $data['print_at'], 'sort' => $data['sort'] ?? 0]);
            $isDefault = $request->boolean('is_default') || ! Department::where('is_default', true)->whereKeyNot($department->id)->exists();
            if ($isDefault) {
                Department::where('is_default', true)->whereKeyNot($department->id)->update(['is_default' => false]);
            }
            $department->is_default = $isDefault;
            $department->save();

            // Seçilmiş kateqoriyalar bu şöbəyə; seçimdən çıxarılanlar əsas şöbəyə (null)
            $ids = array_map('intval', $data['category_ids'] ?? []);
            Category::where('department_id', $department->id)->whereNotIn('id', $ids)->update(['department_id' => null]);
            if ($ids) {
                Category::whereIn('id', $ids)->update(['department_id' => $department->id]);
            }
        });
    }
}
