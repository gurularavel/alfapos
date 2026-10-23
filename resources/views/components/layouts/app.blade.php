@props(['title' => null, 'compact' => false, 'hideErrors' => false])
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @isset($tenant)<meta name="tenant-id" content="{{ $tenant->id }}">@endisset
    <title>{{ $title ? $title.' · ' : '' }}{{ $tenant->name ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $user = auth()->user();
    $nav = $user->isSuperAdmin()
        ? [
            ['admin.tenants.index', 'Bizneslər', 'building', null, 'admin.tenants.*'],
            ['admin.plans.index', 'Planlar', 'crown', null, 'admin.plans.*'],
            ['admin.site.index', 'Sayt', 'store', null, 'admin.site.*'],
        ]
        : [
            ['dashboard', 'Dashboard', 'dashboard', 'DASHBOARD', 'dashboard'],
            ['pos', 'POS', 'pos', 'POS', 'pos'],
            ['orders.index', 'Sifarişlər', 'orders', 'ORDERS', 'orders.*'],
            ['kitchen', 'Mətbəx', 'kitchen', 'KITCHEN', 'kitchen'],
            ['ready-stock.index', 'Hazır yemək', 'chef', 'KITCHEN,INVENTORY', 'ready-stock.*'],
            ['staff-meals.index', 'Personal yeməyi', 'utensils', 'STAFF_MEALS,KITCHEN', 'staff-meals.*'],
            ['settings.menu-items.index', 'Menyu', 'note', 'SETTINGS', 'settings.menu-items.*'],
            ['settings.categories.index', 'Kateqoriyalar', 'tag', 'SETTINGS', 'settings.categories.*'],
            ['reservations.index', 'Rezervlər', 'calendar', 'RESERVATIONS', 'reservations.*'],
            ['customers.index', 'Müştərilər', 'users', 'CUSTOMERS', 'customers.*'],
            ['invoices.index', 'Fakturalar', 'invoice', 'INVOICES', 'invoices.*'],
            ['returns.index', 'Ləğv və qaytarma', 'refresh', 'APPROVE,REPORTS', 'returns.*'],
            ['inventory.index', 'Anbar', 'box', 'INVENTORY', 'inventory.*'],
            ['feedbacks.index', 'Rəylər', 'star', 'FEEDBACK', 'feedbacks.*'],
            ['users.index', 'İstifadəçilər', 'user-cog', 'ADMIN', 'users.*'],
            ['reports.index', 'Hesabatlar', 'chart', 'REPORTS', 'reports.*'],
            ['audit-logs.index', 'Audit log', 'history', 'ADMIN', 'audit-logs.*'],
            ['settings.index', 'Ayarlar', 'settings', 'SETTINGS', 'settings.*'],
        ];
    // Stok bildirişləri: yalnız admin və kassirə. Terminalda PIN ilə açan işçiyə görə (stock-alerts.js → allowed)
    $stockAlerts = isset($tenant) && ($user->canSeeStockAlerts() || app(\App\Support\PosTerminal::class)->active());
    $nav = array_filter($nav, fn ($n) => $n[3] === null || ($n[3] === 'ADMIN' ? $user->isAdmin() : collect(explode(',', $n[3]))->contains(fn ($s) => $user->hasScope($s))));
@endphp
<body @class(['h-full bg-canvas font-sans text-ink antialiased', 'pos-full terminal-mode' => app(\App\Support\PosTerminal::class)->active()]) x-data="{ nav: false }">
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <aside data-shell class="no-print fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-zinc-200/70 bg-[#e9eee6] transition-transform lg:translate-x-0"
           :class="nav && 'translate-x-0'">
        <div class="flex items-center gap-2 px-6 pt-6 pb-5">
            <a href="{{ $user->homeRoute() }}"><x-logo class="text-2xl" /></a>
        </div>
        <div class="mx-4 mb-4 flex items-center gap-3 rounded-2xl bg-white/60 px-3 py-2.5">
            <div class="flex size-10 items-center justify-center rounded-full bg-brand-500 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</div>
            <div class="min-w-0">
                <p class="truncate text-sm font-bold">{{ $user->name }}</p>
                <p class="truncate text-xs font-semibold tracking-wide text-zinc-500 uppercase">{{ $user->roleLabel() }}</p>
            </div>
        </div>
        <nav class="flex-1 space-y-0.5 overflow-y-auto px-4 pb-4">
            {{-- Ən dəqiq uyğun gələn bənd aktivdir: Menyu səhifəsində "Ayarlar" (settings.*) yox, "Menyu" seçilir --}}
            @php($activePattern = collect($nav)->pluck(4)->filter(fn ($p) => request()->routeIs($p))->sortByDesc(fn ($p) => strlen($p))->first())
            @foreach ($nav as [$route, $label, $icon, $scope, $pattern])
                <a href="{{ route($route) }}" @class(['nav-link', 'active' => $pattern === $activePattern])>
                    <x-icon :name="$icon" /> {{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="space-y-0.5 border-t border-zinc-300/50 px-4 py-3">
            <a href="{{ route('profile') }}" @class(['nav-link', 'active' => request()->routeIs('profile')])><x-icon name="user-cog" /> Profil</a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="nav-link w-full"><x-icon name="logout" /> Çıxış</button>
            </form>
            <p class="px-3 pt-2 text-[11px] text-zinc-500">AlfaPOS v{{ config('app.version') }} · Dəstək: <a href="tel:{{ site('phone_intl') }}" class="font-semibold hover:text-brand-700">{{ site('phone') }}</a></p>
        </div>
    </aside>
    <div x-cloak x-show="nav" @click="nav = false" class="fixed inset-0 z-30 bg-black/30 lg:hidden"></div>

    <div data-shell-content class="flex min-w-0 flex-1 flex-col lg:pl-64">
        {{-- Topbar --}}
        <header data-shell class="no-print sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-zinc-200/70 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button class="btn-ghost lg:hidden" @click="nav = true" aria-label="Menyu"><x-icon name="menu" /></button>
            <p class="truncate font-bold text-zinc-700">{{ $tenant->name ?? 'Superadmin paneli' }}</p>
            <div class="ml-auto flex items-center gap-2">
                @isset($tenant)
                    @if ($stockAlerts)<x-stock-alert-bell />@endif
                    @if ($tenant->status === 'trial')
                        <a href="{{ route('subscription') }}" class="badge-amber hidden sm:inline-flex">Sınaq: {{ max(0, (int) now()->diffInDays($tenant->trial_ends_at, false)) }} gün qalıb</a>
                    @endif
                    @if ($user->hasScope('POS') && ! request()->routeIs('pos'))
                        <a href="{{ route('pos') }}" class="btn-primary btn-sm"><x-icon name="pos" class="size-4" /> POS</a>
                    @endif
                @endisset
            </div>
        </header>
        @if ($stockAlerts)<x-stock-alert-banner />@endif

        <main @class(['flex-1', 'p-4 sm:p-6' => ! $compact, 'p-3' => $compact])>
            @if (session('success'))
                <div x-data x-init="toast(@js(session('success')))"></div>
            @endif
            @if (session('warning'))
                <div class="mb-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"><x-icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ session('warning') }}</div>
            @endif
            @if ($errors->any() && ! $hideErrors)
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Toast --}}
<div x-data="{ items: [] }" @toast.window="const t = { id: Date.now(), ...$event.detail }; items.push(t); setTimeout(() => items = items.filter(i => i.id !== t.id), 3500)"
     class="no-print fixed right-4 bottom-4 z-[60] flex flex-col gap-2">
    <template x-for="t in items" :key="t.id">
        <div x-transition class="rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg" :class="t.type === 'error' ? 'bg-red-600' : 'bg-ink'" x-text="t.message"></div>
    </template>
</div>
@auth @unless (auth()->user()->isSuperAdmin()) <x-approval-dialogs /> @endunless @endauth
@stack('scripts')
</body>
</html>
