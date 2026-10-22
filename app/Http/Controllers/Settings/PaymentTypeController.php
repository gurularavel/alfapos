<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentTypeController extends Controller
{
    public const ICONS = ['cash' => 'Nağd', 'card' => 'Kart', 'online' => 'Onlayn', 'other' => 'Digər'];

    public function index()
    {
        return view('settings.payment-types', ['paymentTypes' => PaymentType::orderBy('title')->get(), 'icons' => self::ICONS]);
    }

    public function store(Request $request)
    {
        PaymentType::create($this->validated($request));

        return back()->with('success', 'Ödəniş növü əlavə edildi.');
    }

    public function update(Request $request, PaymentType $paymentType)
    {
        $paymentType->update($this->validated($request));

        return back()->with('success', 'Ödəniş növü yeniləndi.');
    }

    public function destroy(PaymentType $paymentType)
    {
        $paymentType->delete();

        return back()->with('success', 'Ödəniş növü silindi.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:60'],
            'icon' => ['required', Rule::in(array_keys(self::ICONS))],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
