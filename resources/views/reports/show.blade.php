<x-layouts.app :title="$meta[1]">
    <x-page-header :title="$meta[1]" :subtitle="$meta[2]">
        <a href="{{ route('reports.index') }}" class="btn-secondary no-print">← Hesabatlar</a>
        <button onclick="window.print()" class="btn-secondary no-print"><x-icon name="printer" class="size-4" /> Çap</button>
    </x-page-header>

    <form class="card no-print mb-4 flex flex-wrap items-end gap-2 p-3">
        <div><label class="label">Başlanğıc</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="input"></div>
        <div><label class="label">Son</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="input"></div>
        @if ($branches->count() > 1)
            <div><label class="label">Filial</label><select name="branch_id" class="input"><option value="">Bütün filiallar</option>@foreach ($branches as $b)<option value="{{ $b->id }}" @selected($branchId === $b->id)>{{ $b->name }}</option>@endforeach</select></div>
        @endif
        <div><label class="label">Terminal</label><select name="terminal_id" class="input"><option value="">Bütün terminallar</option>@foreach ($terminals as $tm)<option value="{{ $tm->id }}" @selected($terminalId === $tm->id)>{{ $tm->name }}{{ $branches->count() > 1 ? ' · '.$tm->branch?->name : '' }}</option>@endforeach</select></div>
        <button class="btn-primary">Göstər</button>
        <div class="flex flex-wrap gap-1.5">
            @foreach (['Bu gün' => [today(), today()], 'Dünən' => [today()->subDay(), today()->subDay()], '7 gün' => [today()->subDays(6), today()], 'Bu ay' => [today()->startOfMonth(), today()], 'Keçən ay' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()]] as $label => [$f, $t])
                <a href="{{ route('reports.show', array_filter([$key, 'from' => $f->toDateString(), 'to' => $t->toDateString(), 'branch_id' => $branchId, 'terminal_id' => $terminalId])) }}" class="chip">{{ $label }}</a>
            @endforeach
        </div>
    </form>

    <p class="mb-2 text-sm text-zinc-500">Dövr: {{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }}@if ($branchId) · Filial: {{ $branches->firstWhere('id', $branchId)?->name }}@endif @if ($terminalId) · Terminal: {{ $terminals->firstWhere('id', $terminalId)?->name }}@endif</p>
    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr>@foreach ($columns as $i => $col)<th @class(['text-right' => $i > 0])>{{ $col }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $i => $cell)<td @class(['text-right' => $i > 0, 'font-semibold' => $i === 0])>{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($columns) }}"><x-empty icon="chart" title="Bu dövr üçün məlumat yoxdur" /></td></tr>
            @endforelse
            </tbody>
            @if ($summary && $rows)
                <tfoot><tr class="bg-zinc-50 font-black">@foreach ($summary as $i => $cell)<td @class(['px-4 py-3', 'text-right' => $i > 0])>{{ $cell }}</td>@endforeach</tr></tfoot>
            @endif
        </table>
    </div>
</x-layouts.app>
