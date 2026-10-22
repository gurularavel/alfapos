<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return view('settings.categories', ['categories' => Category::withCount('menuItems')->orderBy('sort')->get()]);
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
        ]);

        return $data + ['is_enabled' => $request->boolean('is_enabled'), 'sort' => $data['sort'] ?? 0];
    }
}
