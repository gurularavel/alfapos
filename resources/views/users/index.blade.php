<x-layouts.app title="İstifadəçilər">
@php
    $presets = collect(\App\Models\User::ROLES)->map(fn ($r) => $r[1]);
@endphp
<div x-data="{
        edit: null,
        presets: @js($presets),
        blank: { id: null, name: '', email: '', phone: '', designation: '', role: 'waiter', scopes: @js($presets['waiter']), table_ids: [], terminal_ids: [], is_active: true, has_pin: false },
        setRole(role) { this.edit.role = role; if (this.presets[role]?.length) this.edit.scopes = [...this.presets[role]]; },
     }">
    <x-page-header title="İstifadəçilər" subtitle="İşçilər, rollar, icazələr və POS terminalı üçün PIN kodlar.">
        <button class="btn-primary" @click="edit = JSON.parse(JSON.stringify(blank))"><x-icon name="plus" class="size-4" /> Yeni işçi</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Ad</th><th>Rol</th><th>Login (email)</th><th>PIN</th><th>Terminal</th><th>İcazələr</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr>
                    <td class="font-bold">{{ $u->name }}@if ($u->designation)<p class="text-xs font-normal text-zinc-500">{{ $u->designation }}</p>@endif</td>
                    <td><span class="{{ ['admin' => 'badge-green', 'cashier' => 'badge-blue', 'waiter' => 'badge-amber', 'chef' => 'badge-red'][$u->role] ?? 'badge-gray' }}">{{ $u->roleLabel() }}</span></td>
                    <td class="text-zinc-500">{{ $u->email ?? '— (yalnız PIN)' }}</td>
                    <td>{!! $u->hasPin() ? '<span class="badge-green">✓ var</span>' : '<span class="badge-gray">yoxdur</span>' !!}</td>
                    <td class="text-xs text-zinc-500">{{ $u->isAdmin() ? 'Hamısı' : ($u->terminals->isEmpty() ? 'Hamısı' : $terminals->whereIn('id', $u->terminals->pluck('id'))->pluck('name')->implode(', ')) }}</td>
                    <td class="max-w-xs text-xs text-zinc-500">{{ $u->isAdmin() ? 'Hamısı' : collect($u->scopes)->map(fn ($s) => \App\Models\User::SCOPES[$s] ?? $s)->implode(', ') }}</td>
                    <td>{!! $u->is_active ? '<span class="badge-green">Aktiv</span>' : '<span class="badge-red">Deaktiv</span>' !!}</td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn-ghost btn-sm" @click="edit = @js(['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'designation' => $u->designation, 'role' => $u->role, 'scopes' => $u->scopes ?? [], 'table_ids' => $u->tables->pluck('id'), 'terminal_ids' => $u->terminals->pluck('id'), 'is_active' => $u->is_active, 'has_pin' => $u->hasPin()])"><x-icon name="edit" class="size-4" /></button>
                        @unless ($u->is(auth()->user()))
                            <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline" onsubmit="return confirm('Silinsin?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button></form>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-sm text-zinc-500">PIN kod POS terminalında ekranı açmaq üçündür: işçi PIN-i daxil edəndə sifarişlər avtomatik onun adına yazılır. Ofisiant və kassirlərin email-i olmaya bilər — onlar yalnız PIN ilə işləyir.</p>

    <x-modal show="edit" close="edit = null" title="İşçi" width="max-w-2xl">
        <template x-if="edit"><form method="POST" :action="edit.id ? @js(url('dashboard/users')) + '/' + edit.id : @js(route('users.store'))" id="user-form" class="space-y-4" autocomplete="off">
            @csrf
            <template x-if="edit.id"><input type="hidden" name="_method" value="PUT"></template>
            <div>
                <p class="label">Rol</p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                    @foreach (\App\Models\User::ROLES as $key => [$label])
                        <label class="cursor-pointer rounded-xl border border-zinc-200 px-3 py-2 text-center text-sm font-bold has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="radio" name="role" value="{{ $key }}" :checked="edit.role === '{{ $key }}'" @change="setRole('{{ $key }}')" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label">Ad *</label><input name="name" x-model="edit.name" class="input" required></div>
                <div><label class="label">Vəzifə</label><input name="designation" x-model="edit.designation" class="input" placeholder="Baş ofisiant, barmen..."></div>
                <div>
                    <label class="label">PIN kod (4 rəqəm)</label>
                    <input name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" class="input font-mono tracking-[0.5em]" :placeholder="edit.has_pin ? '•••• (dəyişmək üçün yazın)' : '1234'" autocomplete="off">
                    <label x-show="edit.has_pin" class="mt-1 flex items-center gap-1.5 text-xs text-red-600"><input type="checkbox" name="remove_pin" value="1" class="rounded"> PIN-i sil</label>
                </div>
                <div><label class="label">Telefon</label><input name="phone" x-model="edit.phone" class="input"></div>
                <div><label class="label">Email (panelə login) <span x-show="edit.role !== 'admin'" class="font-normal text-zinc-400">— istəyə bağlı</span></label><input name="email" type="email" x-model="edit.email" class="input" :required="edit.role === 'admin'" autocomplete="off"></div>
                <div><label class="label">Parol <span x-show="edit.id" class="font-normal text-zinc-400">(dəyişmək üçün)</span></label><input name="password" type="password" class="input" :required="edit.role === 'admin' && !edit.id" autocomplete="new-password"></div>
            </div>
            <div x-show="edit.role !== 'admin'">
                <p class="label">İcazələr <span class="font-normal text-zinc-400">(rola görə avtomatik seçilir, dəyişə bilərsiniz)</span></p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach (\App\Models\User::SCOPES as $key => $label)
                        <label class="flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50"><input type="checkbox" name="scopes[]" value="{{ $key }}" x-model="edit.scopes" class="rounded text-brand-500"> {{ $label }}</label>
                    @endforeach
                </div>
            </div>
            @if ($terminals->count() > 1)
                <div x-show="edit.role !== 'admin'">
                    <p class="label">Terminallar <span class="font-normal text-zinc-400">— PIN yalnız seçilmiş terminallarda işləyir; heç biri seçilməsə hamısında</span></p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($terminals as $tm)
                            <label class="flex items-center gap-1.5 rounded-lg border border-zinc-200 px-2.5 py-1.5 text-sm has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50"><input type="checkbox" name="terminal_ids[]" value="{{ $tm->id }}" x-model.number="edit.terminal_ids" class="rounded text-brand-500"> {{ $tm->name }} <span class="text-xs text-zinc-400">{{ $tm->branch?->name }}</span></label>
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($tables->isNotEmpty())
                <div x-show="edit.role === 'waiter'">
                    <p class="label">Təyin olunmuş masalar</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($tables as $t)
                            <label class="flex items-center gap-1.5 rounded-lg border border-zinc-200 px-2.5 py-1.5 text-sm has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50"><input type="checkbox" name="table_ids[]" value="{{ $t->id }}" x-model.number="edit.table_ids" class="rounded text-brand-500"> {{ $t->title }}</label>
                        @endforeach
                    </div>
                </div>
            @endif
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" x-model="edit.is_active" class="rounded text-brand-500"> Aktiv</label>
        </form></template>
        <x-slot:footer><button class="btn-secondary" @click="edit = null">Bağla</button><button class="btn-primary" form="user-form">Saxla</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
