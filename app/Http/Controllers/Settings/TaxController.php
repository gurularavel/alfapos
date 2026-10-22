<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxController extends Controller
{
    public function index()
    {
        return view('settings.taxes', ['taxes' => Tax::orderBy('title')->get()]);
    }

    public function store(Request $request)
    {
        Tax::create($this->validated($request));

        return back()->with('success', 'Vergi əlavə edildi.');
    }

    public function update(Request $request, Tax $tax)
    {
        $tax->update($this->validated($request));

        return back()->with('success', 'Vergi yeniləndi.');
    }

    public function destroy(Tax $tax)
    {
        $tax->delete();

        return back()->with('success', 'Vergi silindi.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:60'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'type' => ['required', Rule::in(['inclusive', 'exclusive'])],
        ]);
    }
}
