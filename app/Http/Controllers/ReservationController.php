<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\StoreTable;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date');

        $reservations = Reservation::with('table')
            ->when($date, fn ($q) => $q->whereDate('reserved_at', $date), fn ($q) => $q->where('reserved_at', '>=', today()))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('customer_name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->orderBy('reserved_at')->paginate(30)->withQueryString();

        return view('reservations.index', [
            'reservations' => $reservations,
            'tables' => StoreTable::orderBy('title')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Reservation::create($this->validated($request));

        return back()->with('success', 'Rezerv əlavə edildi.');
    }

    public function update(Request $request, Reservation $reservation)
    {
        $reservation->update($this->validated($request));

        return back()->with('success', 'Rezerv yeniləndi.');
    }

    public function destroy(Reservation $reservation)
    {
        $reservation->delete();

        return back()->with('success', 'Rezerv silindi.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'store_table_id' => ['nullable', Rule::exists('store_tables', 'id')->where('tenant_id', app(Tenancy::class)->id())],
            'reserved_at' => ['required', 'date'],
            'people' => ['required', 'integer', 'min:1', 'max:100'],
            'status' => ['required', Rule::in(array_keys(Reservation::STATUSES))],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
