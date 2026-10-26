<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Department;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return view('settings.categories', [
            'categories' => Category::with('department:id,name')->withCount('menuItems')->orderBy('sort')->get(),
            'departments' => Department::orderBy('sort')->orderBy('id')->get(['id', 'name', 'is_default']),
        ]);
    }

    public function store(Request $request)
    {
        Category::create($this->validated($request));

        return back()->with('success', 'Kateqoriya əlavə edildi.');
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request));

        return back()->with('success', 'Kateqoriya yeniləndi.');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return back()->with('success', 'Kateqoriya silindi.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:60'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', app(Tenancy::class)->id())],
        ]);

        return $data + ['is_enabled' => $request->boolean('is_enabled'), 'sort' => $data['sort'] ?? 0];
    }
}
