<x-layouts.app title="Rezervlər">
<div x-data="{ edit: null, blank: { id: null, customer_name: '', phone: '', store_table_id: '', reserved_at: @js(now()->addHour()->startOfHour()->format('Y-m-d\TH:i')), people: 2, status: 'booked', notes: '' } }">
    <x-page-header title="Rezervlər" subtitle="Gələcək masa rezervləri.">
        <button class="btn-primary" @click="edit = { ...blank }"><x-icon name="plus" class="size-4" /> Yeni rezerv</button>
    </x-page-header>

    <form class="card mb-4 grid gap-2 p-3 sm:grid-cols-[1fr_auto_auto]">
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Ad və ya telefon">
        <input type="date" name="date" value="{{ request('date') }}" class="input">
        <button class="btn-primary"><x-icon name="search" class="size-4" /> Axtar</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Tarix / saat</th><th>Müştəri</th><th>Telefon</th><th>Masa</th><th>Nəfər</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($reservations as $r)
                <tr>
                    <td class="font-bold whitespace-nowrap">{{ $r->reserved_at->format('d.m.Y H:i') }}</td>
                    <td>{{ $r->customer_name }}@if ($r->notes)<p class="text-xs text-zinc-500">{{ $r->notes }}</p>@endif</td>
                    <td>{{ $r->phone }}</td>
                    <td>{{ $r->table?->label() ?? '—' }}</td>
                    <td>{{ $r->people }}</td>
                    <td><span class="{{ ['booked' => 'badge-blue', 'arrived' => 'badge-green', 'cancelled' => 'badge-gray'][$r->status] }}">{{ \App\Models\Reservation::STATUSES[$r->status] }}</span></td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn-ghost btn-sm" @click="edit = @js(['id' => $r->id, 'customer_name' => $r->customer_name, 'phone' => $r->phone, 'store_table_id' => $r->store_table_id ?? '', 'reserved_at' => $r->reserved_at->format('Y-m-d\TH:i'), 'people' => $r->people, 'status' => $r->status, 'notes' => $r->notes])"><x-icon name="edit" class="size-4" /></button>
                        <form method="POST" action="{{ route('reservations.destroy', $r) }}" class="inline" onsubmit="return confirm('Silinsin?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty icon="calendar" title="Rezerv yoxdur" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $reservations->links() }}</div>

    <x-modal show="edit" close="edit = null" title="Rezerv">
        <template x-if="edit"><form method="POST" :action="edit?.id ? @js(url('dashboard/reservations')) + '/' + edit.id : @js(route('reservations.store'))" id="res-form" class="grid gap-3 sm:grid-cols-2">
            @csrf
            <template x-if="edit?.id"><input type="hidden" name="_method" value="PUT"></template>
            <div><label class="label">Müştəri adı *</label><input name="customer_name" x-model="edit.customer_name" class="input" required></div>
            <div><label class="label">Telefon</label><input name="phone" x-model="edit.phone" class="input"></div>
            <div><label class="label">Tarix və saat *</label><input type="datetime-local" name="reserved_at" x-model="edit.reserved_at" class="input" required></div>
            <div><label class="label">Nəfər *</label><input type="number" min="1" name="people" x-model="edit.people" class="input" required></div>
            <div><label class="label">Masa</label>
                <select name="store_table_id" x-model="edit.store_table_id" class="input"><option value="">—</option>@foreach ($tables as $t)<option value="{{ $t->id }}">{{ $t->label() }} ({{ $t->seating_capacity }})</option>@endforeach</select>
            </div>
            <div><label class="label">Status</label>
                <select name="status" x-model="edit.status" class="input">@foreach (\App\Models\Reservation::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            </div>
            <div class="sm:col-span-2"><label class="label">Qeyd</label><input name="notes" x-model="edit.notes" class="input"></div>
        </form></template>
        <x-slot:footer>
            <button class="btn-secondary" @click="edit = null">Bağla</button>
            <button class="btn-primary" form="res-form">Saxla</button>
        </x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
