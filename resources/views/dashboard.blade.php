<x-layouts.app title="Dashboard">
@php
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Sabahınız xeyir' : ($hour < 18 ? 'Günortanız xeyir' : 'Axşamınız xeyir');
    $goalPct = $goal > 0 ? min(100, round($revenueToday / $goal * 100)) : null;
    $max = max(1, $trend->max('value'));
@endphp

<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <p class="text-xs font-bold tracking-widest text-brand-600 uppercase">{{ $greeting }}</p>
        <h1 class="text-2xl font-extrabold">Xoş gəldiniz, {{ auth()->user()->name }} 👋</h1>
        <p class="text-sm text-zinc-500">Bu gün restoranınızda baş verənlər.</p>
    </div>
    <a href="{{ route('reports.index') }}" class="btn-secondary"><x-icon name="chart" class="size-4" /> Hesabatlar</a>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @php
        $kpis = [
            ['Bugünkü gəlir', money($revenueToday), $revenueChange],
            ['Bugünkü sifarişlər', $ordersToday, $ordersChange],
            ['Aktiv sifarişlər', $activeOrders, null],
            ['Servis haqqı / vergi', money($serviceToday).' / '.money($taxToday), null],
        ];
    @endphp
    @foreach ($kpis as [$label, $value, $change])
        <div class="card card-pad">
            <p class="text-sm font-semibold text-zinc-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-black">{{ $value }}</p>
            @if ($change !== null)
                <p @class(['mt-1 text-xs font-bold', 'text-brand-600' => $change >= 0, 'text-red-600' => $change < 0])>{{ $change >= 0 ? '▲' : '▼' }} {{ abs($change) }}% <span class="font-normal text-zinc-500">dünənlə müqayisədə</span></p>
            @endif
        </div>
    @endforeach
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-3">
    <div class="card card-pad xl:col-span-2">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-extrabold">Gəlir trendi</h2><span class="text-xs text-zinc-500">Son 7 gün</span>
        </div>
        <div class="flex h-56 items-end gap-2 sm:gap-4">
            @foreach ($trend as $day)
                <div class="group flex h-full flex-1 flex-col items-center justify-end gap-1.5">
                    <span class="text-[11px] font-bold text-zinc-500 opacity-0 transition group-hover:opacity-100">{{ number_format($day['value'], 0, '.', ' ') }}</span>
                    <div class="w-full max-w-12 rounded-t-lg {{ $loop->last ? 'bg-brand-500' : 'bg-brand-200 group-hover:bg-brand-300' }}" style="height: {{ max(2, $day['value'] / $max * 100) }}%"></div>
                    <span class="text-[11px] whitespace-nowrap text-zinc-500">{{ $day['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card card-pad">
        <h2 class="mb-4 font-extrabold">Günlük satış hədəfi</h2>
        @if ($goalPct !== null)
            <div class="flex items-center gap-5">
                <div class="relative size-32 shrink-0">
                    <svg viewBox="0 0 36 36" class="size-full -rotate-90"><circle cx="18" cy="18" r="15.9" fill="none" stroke="#e4e4e7" stroke-width="3.5"/><circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--color-brand-500)" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="{{ $goalPct }} 100"/></svg>
                    <span class="absolute inset-0 flex items-center justify-center text-2xl font-black">{{ $goalPct }}%</span>
                </div>
                <div class="text-sm"><p class="font-bold">{{ money($revenueToday) }}</p><p class="text-zinc-500">/ {{ money($goal) }}</p></div>
            </div>
        @else
            <p class="text-sm text-zinc-500">Hədəf təyin edilməyib. <a href="{{ route('settings.store') }}" class="text-brand-600 hover:underline">Ayarlarda</a> günlük hədəf qoyun.</p>
        @endif
    </div>
</div>

{{-- Saatlar üzrə satış (bu gün) — keçən həftənin eyni günü ilə müqayisə --}}
@php
    $hourMax = max(1, $hourly->max(fn ($h) => max($h['total'], $h['last_week'])));
    $peak = $hourly->sortByDesc('total')->first();
    $nowHour = (int) now()->format('G');
    $todaySum = $hourly->sum('total');
    $lastWeekSum = $hourly->sum('last_week');
    $vsLastWeek = $lastWeekSum > 0 ? round(($todaySum - $lastWeekSum) / $lastWeekSum * 100, 1) : null;
@endphp
<div class="card card-pad mt-4">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-extrabold">Saatlar üzrə satış <span class="text-xs font-normal text-zinc-500">bu gün</span></h2>
            <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-500">
                @if ($peak && $peak['total'] > 0)
                    <span>Pik saat: <b class="text-ink">{{ sprintf('%02d:00–%02d:00', $peak['hour'], $peak['hour'] + 1) }}</b> · {{ money($peak['total']) }} · {{ $peak['checks'] }} çek</span>
                @endif
                @if ($vsLastWeek !== null)
                    <span>Keçən {{ today()->subWeek()->translatedFormat('l') }} ilə müqayisə: <b @class(['text-brand-600' => $vsLastWeek >= 0, 'text-red-600' => $vsLastWeek < 0])>{{ $vsLastWeek >= 0 ? '▲' : '▼' }} {{ abs($vsLastWeek) }}%</b></span>
                @endif
            </p>
        </div>
        <div class="flex items-center gap-4 text-xs text-zinc-500">
            <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-brand-400"></span> Bu gün</span>
            <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-zinc-200"></span> Keçən həftə</span>
            <a href="{{ route('reports.show', ['sales-by-hour', 'from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="font-semibold text-brand-700 hover:underline">Hesabat →</a>
        </div>
    </div>
    <div class="flex h-48 items-end gap-1 sm:gap-1.5">
        @foreach ($hourly as $h)
            @php($isPeak = $peak && $peak['total'] > 0 && $h['hour'] === $peak['hour'])
            <div class="group relative flex h-full min-w-0 flex-1 flex-col items-center justify-end"
                 title="{{ sprintf('%02d:00', $h['hour']) }} — bu gün {{ money($h['total']) }} ({{ $h['checks'] }} çek), keçən həftə {{ money($h['last_week']) }}">
                <span class="mb-1 hidden text-[10px] font-bold whitespace-nowrap text-zinc-600 group-hover:block">{{ number_format($h['total'], 0, '.', ' ') }}</span>
                <div class="relative w-full max-w-10 flex-1">
                    <div class="absolute inset-x-0 bottom-0 rounded-t-md bg-zinc-200" style="height: {{ $h['last_week'] / $hourMax * 100 }}%"></div>
                    <div @class([
                            'absolute inset-x-1 bottom-0 rounded-t-md',
                            'bg-brand-600' => $isPeak,
                            'bg-brand-400 group-hover:bg-brand-500' => ! $isPeak,
                            'ring-2 ring-amber-400 ring-offset-1' => $h['hour'] === $nowHour,
                        ]) style="height: {{ max($h['total'] > 0 ? 2 : 0, $h['total'] / $hourMax * 100) }}%"></div>
                </div>
                <span @class(['mt-1.5 text-[10px] tabular-nums sm:text-[11px]', 'font-bold text-amber-600' => $h['hour'] === $nowHour, 'text-zinc-500' => $h['hour'] !== $nowHour])>{{ sprintf('%02d', $h['hour']) }}</span>
            </div>
        @endforeach
    </div>
</div>

<div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <div class="card card-pad">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-extrabold">Kateqoriyalar üzrə satış <span class="text-xs font-normal text-zinc-500">bu gün</span></h2>
            <a href="{{ route('reports.show', ['category-sales', 'from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="text-xs font-semibold text-brand-700 hover:underline">Hesabat →</a>
        </div>
        @php($categoryTotal = max(1, (float) $categorySales->sum('amount')))
        @php($categoryColors = ['bg-brand-500', 'bg-sky-500', 'bg-amber-400', 'bg-violet-500', 'bg-rose-400', 'bg-teal-400'])
        @php($categoryTop = $categorySales->take(6))
        @php($categoryRest = $categorySales->slice(6))
        @if ($categorySales->isNotEmpty())
            {{-- Paylar bir zolaqda --}}
            <div class="mb-4 flex h-3 overflow-hidden rounded-full bg-zinc-100">
                @foreach ($categoryTop as $i => $row)
                    <div class="{{ $categoryColors[$i] }} h-full" style="width: {{ $row->amount / $categoryTotal * 100 }}%" title="{{ $row->title }} — {{ money($row->amount) }}"></div>
                @endforeach
                @if ($categoryRest->isNotEmpty())<div class="h-full bg-zinc-300" style="width: {{ $categoryRest->sum('amount') / $categoryTotal * 100 }}%" title="Digər"></div>@endif
            </div>
        @endif
        @forelse ($categoryTop as $i => $row)
            <div class="flex items-center gap-2 py-1 text-sm">
                <span class="size-2.5 shrink-0 rounded-full {{ $categoryColors[$i] }}"></span>
                <span @class(['min-w-0 flex-1 truncate font-semibold', 'text-zinc-500' => ! $row->id])>{{ $row->title }}</span>
                <span class="shrink-0 text-xs text-zinc-500">{{ (float) $row->qty }} əd · {{ round($row->amount / $categoryTotal * 100) }}%</span>
                <span class="w-20 shrink-0 text-right font-bold tabular-nums">{{ money($row->amount) }}</span>
            </div>
        @empty
            <p class="text-sm text-zinc-500">Bu gün satış yoxdur.</p>
        @endforelse
        @if ($categoryRest->isNotEmpty())
            <div class="flex items-center gap-2 py-1 text-sm text-zinc-500">
                <span class="size-2.5 shrink-0 rounded-full bg-zinc-300"></span>
                <span class="flex-1">Digər ({{ $categoryRest->count() }})</span>
                <span class="w-20 text-right font-bold tabular-nums">{{ money($categoryRest->sum('amount')) }}</span>
            </div>
        @endif
    </div>

    <div class="card card-pad">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-extrabold">Masalar üzrə satış <span class="text-xs font-normal text-zinc-500">bu gün</span></h2>
            <a href="{{ route('reports.show', ['sales-by-table', 'from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="text-xs font-semibold text-brand-700 hover:underline">Hesabat →</a>
        </div>
        <div class="mb-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl bg-zinc-50 p-2"><p class="text-lg font-extrabold tabular-nums">{{ $tableStats->used }}<span class="text-xs font-normal text-zinc-400">/{{ $tableStats->tables }}</span></p><p class="text-[11px] text-zinc-500">satış olan masa</p></div>
            <div class="rounded-xl bg-amber-50 p-2"><p class="text-lg font-extrabold text-amber-700 tabular-nums">{{ $tableStats->occupied }}</p><p class="text-[11px] text-zinc-500">hazırda dolu</p></div>
            <div class="rounded-xl bg-zinc-50 p-2"><p class="text-lg font-extrabold tabular-nums">{{ $tableStats->checks ? money($tableStats->total / $tableStats->checks) : '—' }}</p><p class="text-[11px] text-zinc-500">orta masa çeki</p></div>
        </div>
        @php($tableMax = max(1, (float) $tableSales->max('total')))
        @forelse ($tableSales as $row)
            <div class="py-1.5 text-sm">
                <div class="mb-1 flex items-center justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-1.5">
                        <span class="truncate font-semibold">{{ $row->title }}</span>
                        @if ($branchCount > 1 && $row->branch)<span class="shrink-0 text-xs text-zinc-400">· {{ $row->branch }}</span>@endif
                        @if ($row->open_total !== null)<span class="badge-amber shrink-0" title="Açıq sifariş: {{ money($row->open_total) }}">dolu</span>@endif
                    </span>
                    <span class="shrink-0 tabular-nums"><span class="text-xs text-zinc-500">{{ $row->checks }} çek ·</span> <b>{{ money($row->total) }}</b></span>
                </div>
                <div class="h-1.5 rounded-full bg-zinc-100"><div class="h-1.5 rounded-full bg-teal-500" style="width: {{ $row->total / $tableMax * 100 }}%"></div></div>
            </div>
        @empty
            <p class="text-sm text-zinc-500">Bu gün masalardan satış yoxdur.</p>
        @endforelse
        @if ($tableStats->used > $tableSales->count())
            <p class="mt-2 text-xs text-zinc-400">Ən çox satan {{ $tableSales->count() }} masa göstərilir.</p>
        @endif
    </div>

    <div class="card card-pad">
        <h2 class="mb-3 font-extrabold">Top məhsullar <span class="text-xs font-normal text-zinc-500">bu gün</span></h2>
        @forelse ($topItems as $i => $row)
            <div class="flex items-center gap-3 py-1.5 text-sm">
                <span class="flex size-6 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">{{ $i + 1 }}</span>
                <span class="flex-1 truncate font-semibold">{{ $row->title }}</span>
                <span class="text-zinc-500">{{ $row->qty }} əd.</span>
            </div>
        @empty
            <p class="text-sm text-zinc-500">Bu gün satış yoxdur.</p>
        @endforelse
    </div>

    <div class="card card-pad">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-extrabold">İşçilər üzrə satış <span class="text-xs font-normal text-zinc-500">bu gün</span></h2>
            <a href="{{ route('reports.show', ['staff-orders', 'from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="text-xs font-semibold text-brand-700 hover:underline">Hesabat →</a>
        </div>
        @php($staffMax = max(1, (float) $staffSales->max(fn ($r) => max($r->orders_total, $r->collected))))
        @forelse ($staffSales as $i => $row)
            <div class="mb-3 text-sm">
                <div class="mb-1 flex items-center justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">{{ $i + 1 }}</span>
                        <span class="truncate font-semibold">{{ $row->name }}</span>
                        <span class="shrink-0 text-xs text-zinc-400">{{ $row->role }}</span>
                    </span>
                    <span class="shrink-0 font-bold tabular-nums">{{ money(max($row->orders_total, $row->collected)) }}</span>
                </div>
                @if ($row->orders)
                    <div class="h-1.5 rounded-full bg-zinc-100"><div class="h-1.5 rounded-full bg-amber-400" style="width: {{ $row->orders_total / $staffMax * 100 }}%"></div></div>
                @endif
                @if ($row->checks)
                    <div class="mt-1 h-1.5 rounded-full bg-zinc-100"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ $row->collected / $staffMax * 100 }}%"></div></div>
                @endif
                <p class="mt-1 text-xs text-zinc-500">
                    @if ($row->orders)<span class="text-amber-600">●</span> {{ $row->orders }} sifariş · {{ money($row->orders_total) }}@endif
                    @if ($row->orders && $row->checks) &nbsp; @endif
                    @if ($row->checks)<span class="text-brand-600">●</span> kassa: {{ $row->checks }} çek · {{ money($row->collected) }}@endif
                </p>
            </div>
        @empty
            <p class="text-sm text-zinc-500">Bu gün satış yoxdur.</p>
        @endforelse
        @if ($staffSales->isNotEmpty())
            <p class="mt-2 text-xs text-zinc-400"><span class="text-amber-500">●</span> qəbul etdiyi sifarişlər &nbsp; <span class="text-brand-500">●</span> kassaya yığdığı çeklər</p>
        @endif
    </div>

    <div class="card card-pad">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-extrabold">Filiallar üzrə satış <span class="text-xs font-normal text-zinc-500">bu gün</span></h2>
            <a href="{{ route('reports.show', ['sales-by-branch', 'from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="text-xs font-semibold text-brand-700 hover:underline">Hesabat →</a>
        </div>
        @php($branchTotal = max(1, (float) $branchSales->sum('total')))
        @forelse ($branchSales as $row)
            <div class="mb-3 text-sm">
                <div class="mb-1 flex justify-between gap-2">
                    <span @class(['truncate font-semibold', 'text-zinc-500' => ! $row->branch_id])>{{ $row->name }}</span>
                    <span class="shrink-0 font-bold tabular-nums">{{ money($row->total) }}</span>
                </div>
                <div class="h-2 rounded-full bg-zinc-100"><div class="h-2 rounded-full {{ $row->branch_id ? 'bg-sky-500' : 'bg-zinc-300' }}" style="width: {{ $row->total / $branchTotal * 100 }}%"></div></div>
                <p class="mt-1 text-xs text-zinc-500">{{ $row->checks }} çek · orta çek {{ money($row->total / max(1, $row->checks)) }} · {{ round($row->total / $branchTotal * 100) }}%</p>
            </div>
        @empty
            <p class="text-sm text-zinc-500">Bu gün satış yoxdur.</p>
        @endforelse
        @if ($branchCount === 1 && auth()->user()->hasScope('SETTINGS'))
            <p class="mt-2 text-xs text-zinc-400">Bir filialınız var. Yeni filial: <a href="{{ route('settings.branches.index') }}" class="text-brand-700 hover:underline">Ayarlar → Filiallar</a></p>
        @endif
    </div>

    <div class="card card-pad">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-extrabold">Terminallar üzrə satış <span class="text-xs font-normal text-zinc-500">bu gün</span></h2>
            <a href="{{ route('reports.show', ['sales-by-terminal', 'from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="text-xs font-semibold text-brand-700 hover:underline">Hesabat →</a>
        </div>
        @php($terminalTotal = max(1, (float) $terminalSales->sum('total')))
        @forelse ($terminalSales as $row)
            <div class="mb-2.5 text-sm">
                <div class="mb-1 flex justify-between gap-2">
                    <span class="truncate">
                        <span @class(['font-semibold', 'text-zinc-500' => ! $row->terminal_id])>{{ $row->name }}</span>
                        @if ($branchCount > 1 && $row->branch)<span class="text-xs text-zinc-400">· {{ $row->branch }}</span>@endif
                    </span>
                    <span class="shrink-0 tabular-nums"><span class="text-xs text-zinc-500">{{ $row->checks }} çek ·</span> {{ money($row->total) }}</span>
                </div>
                <div class="h-2 rounded-full bg-zinc-100"><div class="h-2 rounded-full {{ $row->terminal_id ? 'bg-violet-500' : 'bg-zinc-300' }}" style="width: {{ $row->total / $terminalTotal * 100 }}%"></div></div>
            </div>
        @empty
            <p class="text-sm text-zinc-500">Bu gün satış yoxdur.</p>
        @endforelse
    </div>

    <div class="card card-pad">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-extrabold">Sifariş növləri üzrə satış <span class="text-xs font-normal text-zinc-500">bu gün</span></h2>
            <a href="{{ route('reports.show', ['sales-by-order-type', 'from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="text-xs font-semibold text-brand-700 hover:underline">Hesabat →</a>
        </div>
        @php($typeTotal = max(1, (float) $orderTypes->sum('total')))
        @php($typeStyles = ['dinein' => ['bg-brand-500', 'table'], 'takeaway' => ['bg-amber-400', 'bag'], 'delivery' => ['bg-sky-500', 'truck']])
        <div class="grid grid-cols-3 gap-2">
            @foreach ($orderTypes as $row)
                <div class="rounded-xl border border-zinc-100 p-3 text-center">
                    <div class="mx-auto mb-1.5 flex size-8 items-center justify-center rounded-full {{ $typeStyles[$row->type][0] ?? 'bg-zinc-400' }} text-white"><x-icon :name="$typeStyles[$row->type][1] ?? 'receipt'" class="size-4" /></div>
                    <p class="text-xs font-semibold text-zinc-500">{{ $row->label }}</p>
                    <p class="font-extrabold tabular-nums">{{ money($row->total) }}</p>
                    <p class="text-[11px] text-zinc-500">{{ round($row->total / $typeTotal * 100) }}%</p>
                </div>
            @endforeach
        </div>
        <div class="mt-3 flex h-2.5 overflow-hidden rounded-full bg-zinc-100">
            @foreach ($orderTypes as $row)
                <div class="{{ $typeStyles[$row->type][0] ?? 'bg-zinc-400' }} h-full" style="width: {{ $row->total / $typeTotal * 100 }}%"></div>
            @endforeach
        </div>
        <table class="mt-3 w-full text-sm">
            <thead><tr class="text-xs text-zinc-500"><th class="py-1 text-left font-semibold">Növ</th><th class="text-right font-semibold">Çek</th><th class="text-right font-semibold">Orta çek</th><th class="text-right font-semibold">Açıq</th></tr></thead>
            <tbody>
                @foreach ($orderTypes as $row)
                    <tr class="border-t border-zinc-100">
                        <td class="py-1.5 font-semibold">{{ $row->label }}</td>
                        <td class="text-right tabular-nums">{{ $row->checks }}</td>
                        <td class="text-right tabular-nums">{{ $row->checks ? money($row->total / $row->checks) : '—' }}</td>
                        <td @class(['text-right tabular-nums', 'font-bold text-amber-600' => $row->open, 'text-zinc-400' => ! $row->open])>{{ $row->open }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="mt-2 text-xs text-zinc-400">"Açıq" — bu gün verilmiş, hələ ödənilməmiş sifarişlər.</p>
    </div>

    <div class="card card-pad">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-extrabold">Ödəniş növləri üzrə satış <span class="text-xs font-normal text-zinc-500">bu gün</span></h2>
            <a href="{{ route('reports.show', ['payment-summary', 'from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="text-xs font-semibold text-brand-700 hover:underline">Hesabat →</a>
        </div>
        @php($mixTotal = max(1, (float) $paymentMix->sum('total')))
        @php($payColors = ['cash' => 'bg-brand-500', 'card' => 'bg-sky-500', 'online' => 'bg-violet-500', 'other' => 'bg-amber-400'])
        @if ($paymentMix->isNotEmpty())
            <div class="mb-4 flex h-3 overflow-hidden rounded-full bg-zinc-100">
                @foreach ($paymentMix as $row)
                    <div class="{{ $payColors[$row->icon] ?? 'bg-zinc-400' }} h-full" style="width: {{ $row->total / $mixTotal * 100 }}%" title="{{ $row->title }} — {{ money($row->total) }}"></div>
                @endforeach
            </div>
        @endif
        @forelse ($paymentMix as $row)
            <div class="flex items-center gap-3 py-1.5 text-sm">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $payColors[$row->icon] ?? 'bg-zinc-400' }} text-white"><x-icon :name="$row->icon" class="size-4" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold">{{ $row->title }}</span>
                    <span class="text-xs text-zinc-500">{{ $row->checks }} çek · orta {{ money($row->total / max(1, $row->checks)) }} · {{ round($row->total / $mixTotal * 100) }}%</span>
                </span>
                <span class="shrink-0 text-right">
                    <span class="block font-bold tabular-nums">{{ money($row->total) }}</span>
                    @if ($row->change !== null)
                        <span @class(['text-[11px] font-semibold', 'text-brand-600' => $row->change >= 0, 'text-red-600' => $row->change < 0])>{{ $row->change >= 0 ? '▲' : '▼' }} {{ abs($row->change) }}%</span>
                    @endif
                </span>
            </div>
        @empty
            <p class="text-sm text-zinc-500">Bu gün ödəniş yoxdur.</p>
        @endforelse
        @if ($paymentMix->contains(fn ($r) => $r->change !== null))
            <p class="mt-2 text-xs text-zinc-400">▲▼ — dünənin eyni ödəniş növü ilə müqayisə.</p>
        @endif
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h2 class="mb-3 flex items-center gap-2 font-extrabold"><x-icon name="alert" class="size-4 text-amber-500" /> Az qalan stok</h2>
            @forelse ($lowStock as $item)
                <div class="flex justify-between py-1 text-sm"><span>{{ $item->title }}</span><span @class(['font-bold', 'text-red-600' => $item->quantity <= 0, 'text-amber-600' => $item->quantity > 0])>{{ qty($item->quantity) }} {{ $item->unit }}</span></div>
            @empty
                <p class="text-sm text-zinc-500">Hər şey qaydasındadır.</p>
            @endforelse
        </div>
        <div class="card card-pad">
            <h2 class="mb-3 font-extrabold">Bugünkü rezervlər</h2>
            @forelse ($reservations as $r)
                <div class="flex justify-between py-1 text-sm"><span>{{ $r->customer_name }} · {{ $r->people }} nəfər</span><span class="font-bold">{{ $r->reserved_at->format('H:i') }}</span></div>
            @empty
                <p class="text-sm text-zinc-500">Rezerv yoxdur.</p>
            @endforelse
        </div>
        <div class="card card-pad">
            <h2 class="mb-3 font-extrabold">Son rəylər</h2>
            @forelse ($feedbacks as $f)
                <div class="py-1.5 text-sm"><p class="font-semibold">{{ $f->name ?: 'Anonim' }} <span class="text-amber-500">{{ str_repeat('★', $f->rating) }}</span></p><p class="line-clamp-2 text-zinc-500">{{ $f->comment }}</p></div>
            @empty
                <p class="text-sm text-zinc-500">Hələ rəy yoxdur.</p>
            @endforelse
            <p class="mt-2 text-xs text-zinc-500">Ləğv edilmiş sifarişlər (bu gün): {{ $cancelledToday }}</p>
        </div>
    </div>
</div>
</x-layouts.app>
