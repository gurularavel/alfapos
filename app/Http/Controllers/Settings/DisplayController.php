<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CustomerDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DisplayController extends Controller
{
    public const MAX = 10;

    public function index()
    {
        return view('settings.displays', ['displays' => CustomerDisplay::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        abort_if(CustomerDisplay::count() >= self::MAX, 422, 'Maksimum '.self::MAX.' müştəri ekranı yaratmaq olar.');
        CustomerDisplay::create($request->validate(['name' => ['required', 'string', 'max:60']]));

        return back()->with('success', 'Müştəri ekranı yaradıldı.');
    }

    public function update(Request $request, CustomerDisplay $display)
    {
        $display->update($request->validate(['name' => ['required', 'string', 'max:60']]));

        if ($request->boolean('regenerate')) {
            // Link sızıbsa: köhnə link dərhal işləməz olur
            $display->forceFill(['token' => Str::random(40)])->save();
        }

        return back()->with('success', 'Müştəri ekranı yeniləndi.');
    }

    public function destroy(CustomerDisplay $display)
    {
        $display->delete();

        return back()->with('success', 'Müştəri ekranı silindi.');
    }
}
