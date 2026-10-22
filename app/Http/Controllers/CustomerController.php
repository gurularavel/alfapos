<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::withCount('invoices')->withSum(['invoices' => fn ($q) => $q->where('status', 'paid')], 'total')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->latest()->paginate(25)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function show(Customer $customer)
    {
        $customer->load(['invoices' => fn ($q) => $q->latest()->limit(20)]);

        return view('customers.show', compact('customer'));
    }

    public function store(Request $request)
    {
        Customer::create($this->validated($request));

        return back()->with('success', 'Müştəri əlavə edildi.');
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request, $customer));

        return back()->with('success', 'Müştəri yeniləndi.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return back()->with('success', 'Müştəri silindi.');
    }

    public function export()
    {
        $rows = Customer::orderBy('name')->get(['name', 'phone', 'email', 'birth_date', 'gender', 'created_at']);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel üçün UTF-8 BOM
            fputcsv($out, ['Ad', 'Telefon', 'Email', 'Doğum tarixi', 'Cins', 'Qeydiyyat']);
            foreach ($rows as $c) {
                fputcsv($out, [$c->name, $c->phone, $c->email, $c->birth_date?->format('Y-m-d'), $c->gender, $c->created_at->format('Y-m-d')]);
            }
            fclose($out);
        }, 'musteriler-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'phone' => ['required', 'string', 'max:30', Rule::unique('customers')->where('tenant_id', app(Tenancy::class)->id())->ignore($customer)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'is_member' => ['boolean'],
        ]);
    }
}
