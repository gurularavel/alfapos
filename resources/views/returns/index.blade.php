<x-layouts.app title="Ləğv və qaytarma">
    <x-page-header title="Ləğv və qaytarma" subtitle="Bütün ləğv olunan və qaytarılan məhsullar: kim edib, kim təsdiqləyib, niyə. Fırıldağa nəzarət üçün.">
        <a href="{{ route('reports.show', ['returns-by-staff', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn-secondary"><x-icon name="chart" class="size-4" /> İşçilər üzrə</a>
    </x-page-header>

    <form class="card mb-4 grid gap-2 p-3 sm:grid-cols-[auto_auto_1fr_1fr_auto]">
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="input">
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="input">
        <select name="kind" class="input"><option value="">Ləğv və qaytarma</option>@foreach (\App\Models\ItemReturn::KINDS as $k => $v)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ $v }}</option>@endforeach</select>
        <select name="staff" class="input"><option value="">Bütün işçilər (sifarişi verən)</option>@foreach ($staff as $s)<option value="{{ $s->id }}" @selected(request('staff') == $s->id)>{{ $s->name }}</option>@endforeach</select>
        <button class="btn-primary"><x-icon name="search" class="size-4" /></button>
    </form>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card card-pad border-l-4 border-l-amber-500"><p class="text-sm font-semibold text-zinc-500">Ləğv (ödənişdən əvvəl)</p><p class="text-2xl font-black">{{ $stats->voids }} <span class="text-base font-bold text-zinc-500">· {{ money($stats->void_amount) }}</span></p></div>
        <div class="card card-pad border-l-4 border-l-red-500"><p class="text-sm font-semibold text-zinc-500">Qaytarma (pul geri)</p><p class="text-2xl font-black">{{ $stats->refunds }} <span class="text-base font-bold text-zinc-500">· {{ money($stats->refund_amount) }}</span></p></div>
        <div class="card card-pad border-l-4 border-l-zinc-400"><p class="text-sm font-semibold text-zinc-500">İsraf itkisi (maya)</p><p class="text-2xl font-black">{{ money($stats->waste_cost) }}</p></div>
        <div @class(['card card-pad border-l-4', 'border-l-red-600 bg-red-50' => $stats->after_bill, 'border-l-zinc-300' => ! $stats->after_bill])>
            <p class="text-sm font-semibold text-zinc-500">Hesab çekindən sonra ləğv</p><p class="text-2xl font-black">{{ $stats->after_bill }}</p>
            <p class="text-xs text-zinc-500">Müştəriyə hesab verilib, sonra məhsul silinib — yoxlayın.</p>
        </div>
    </div>

    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Vaxt</th><th>Sifariş</th><th>Məhsul</th><th class="text-right">Məbləğ</th><th>Növ</th><th>Səbəb</th><th>Stok</th><th>Sifarişi verən</th><th>Edən</th><th>Təsdiq</th></tr></thead>
            <tbody>
            @forelse ($returns as $r)
                <tr @class(['bg-red-50/60' => $r->after_bill])>
                    <td class="whitespace-nowrap text-zinc-500">{{ $r->created_at->format('d.m H:i') }}</td>
                    <td class="whitespace-nowrap">#{{ $r->order?->token_no }}@if ($r->order?->table) <span class="text-zinc-500">· {{ $r->order->table->title }}</span>@endif</td>
                    <td class="font-semibold">{{ $r->quantity }} × {{ $r->title }}</td>
                    <td class="text-right font-bold">{{ money($r->amount) }}@if ($r->cost)<span class="block text-xs font-normal text-zinc-500">maya {{ money($r->cost) }}</span>@endif</td>
                    <td>
                        <span class="{{ $r->kind === 'refund' ? 'badge-red' : 'badge-amber' }}">{{ \App\Models\ItemReturn::KINDS[$r->kind] }}</span>
                        @if ($r->after_bill)<span class="badge-red" title="Hesab çeki çap olunandan sonra">çekdən sonra</span>@endif
                    </td>
                    <td>{{ \App\Models\ItemReturn::REASONS[$r->reason] ?? $r->reason }}@if ($r->note)<span class="block text-xs text-zinc-500">{{ $r->note }}</span>@endif</td>
                    <td class="text-xs text-zinc-500">{{ $r->stock_action === 'restore' ? 'Stoka qayıtdı' : 'İsraf' }}</td>
                    <td>{{ $r->orderCreator?->name ?? '—' }}</td>
                    <td>{{ $r->requester?->name ?? '—' }}</td>
                    <td class="font-semibold">{{ $r->approver?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="10"><x-empty icon="check" title="Bu dövrdə ləğv və qaytarma yoxdur" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
