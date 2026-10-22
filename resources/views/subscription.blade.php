<x-layouts.app title="Abunəlik">
    <x-page-header title="Abunəlik" subtitle="Planınız və ödəniş tarixçəsi." />

    <div @class(['card card-pad mb-6 border-l-4', 'border-l-brand-500' => $tenant->hasActiveSubscription(), 'border-l-red-500' => ! $tenant->hasActiveSubscription()])>
        @if ($tenant->hasActiveSubscription())
            <p class="font-bold">{{ $tenant->status === 'trial' ? 'Sınaq müddəti' : 'Aktiv abunəlik' }} · {{ $tenant->plan?->name }}</p>
            <p class="text-sm text-zinc-500">Bitmə tarixi: {{ $tenant->expiresAt()?->format('d.m.Y') }} ({{ (int) now()->diffInDays($tenant->expiresAt()) }} gün qalıb)</p>
        @else
            <p class="font-bold text-red-700">Abunəliyiniz bitib</p>
            <p class="text-sm text-zinc-600">POS və digər bölmələr bağlanıb. Məlumatlarınız qorunur — planı yeniləyən kimi hər şey bərpa olunacaq.</p>
        @endif
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($plans as $plan)
            <div @class(['card card-pad', 'ring-2 ring-brand-500' => $tenant->plan_id === $plan->id])>
                <p class="font-bold text-zinc-500">{{ $plan->name }} @if ($tenant->plan_id === $plan->id)<span class="badge-green">Cari</span>@endif</p>
                <p class="mt-2 text-3xl font-black">{{ rtrim(rtrim($plan->price, '0'), '.') }} <span class="text-base text-zinc-500">{{ $plan->currency }}/{{ $plan->interval === 'year' ? 'il' : 'ay' }}</span></p>
                <ul class="mt-4 space-y-1.5 text-sm text-zinc-600">
                    <li>{{ $plan->max_users ? $plan->max_users.' istifadəçi' : 'Limitsiz istifadəçi' }}</li>
                    <li>{{ $plan->max_menu_items ? $plan->max_menu_items.' menyu məhsulu' : 'Limitsiz menyu' }}</li>
                    <li>{{ $plan->max_tables ? $plan->max_tables.' masa' : 'Limitsiz masa' }}</li>
                </ul>
            </div>
        @endforeach
    </div>
    <p class="mt-4 text-sm text-zinc-500">Planı aktivləşdirmək və ya uzatmaq üçün administratorla əlaqə saxlayın. Onlayn ödəniş tezliklə əlavə olunacaq.</p>

    @if ($tenant->subscriptions->isNotEmpty())
        <div class="card mt-6 overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Plan</th><th>Dövr</th><th class="text-right">Məbləğ</th></tr></thead>
                <tbody>
                @foreach ($tenant->subscriptions->sortByDesc('starts_at') as $s)
                    <tr><td>{{ $s->plan?->name }}</td><td>{{ $s->starts_at->format('d.m.Y') }} — {{ $s->ends_at->format('d.m.Y') }}</td><td class="text-right">{{ $s->amount }} {{ $s->plan?->currency }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
