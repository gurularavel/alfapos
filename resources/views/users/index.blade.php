<x-layouts.app title="İstifadəçilər">
<div x-data="{ edit: null, blank: { id: null, name: '', email: '', phone: '', designation: '', role: 'user', scopes: ['POS', 'ORDERS'], table_ids: [], is_active: true } }">
    <x-page-header title="İstifadəçilər" subtitle="İşçilər, rollar və icazələr.">
        <button class="btn-primary" @click="edit = JSON.parse(JSON.stringify(blank))"><x-icon name="plus" class="size-4" /> Yeni istifadəçi</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Ad</th><th>Email</th><th>Vəzifə</th><th>Rol</th><th>İcazələr</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr>
                    <td class="font-bold">{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td class="text-zinc-500">{{ $u->designation }}</td>
                    <td>{!! $u->isAdmin() ? '<span class="badge-green">Admin</span>' : '<span class="badge-gray">İşçi</span>' !!}</td>
                    <td class="max-w-xs text-xs text-zinc-500">{{ $u->isAdmin() ? 'Hamısı' : collect($u->scopes)->map(fn ($s) => \App\Models\User::SCOPES[$s] ?? $s)->implode(', ') }}</td>
                    <td>{!! $u->is_active ? '<span class="badge-green">Aktiv</span>' : '<span class="badge-red">Deaktiv</span>' !!}</td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn-ghost btn-sm" @click="edit = @js(['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'designation' => $u->designation, 'role' => $u->role, 'scopes' => $u->scopes ?? [], 'table_ids' => $u->tables->pluck('id'), 'is_active' => $u->is_active])"><x-icon name="edit" class="size-4" /></button>
                        @unless ($u->is(auth()->user()))
                            <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline" onsubmit="return confirm('Silinsin?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button></form>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <x-modal show="edit" close="edit = null" title="İstifadəçi" width="max-w-2xl">
        <template x-if="edit"><form method="POST" :action="edit.id ? @js(url('dashboard/users')) + '/' + edit.id : @js(route('users.store'))" id="user-form" class="space-y-4">
            @csrf
            <template x-if="edit.id"><input type="hidden" name="_method" value="PUT"></template>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label">Ad *</label><input name="name" x-model="edit.name" class="input" required></div>
                <div><label class="label">Email (login) *</label><input name="email" type="email" x-model="edit.email" class="input" required></div>
                <div><label class="label">Telefon</label><input name="phone" x-model="edit.phone" class="input"></div>
                <div><label class="label">Vəzifə</label><input name="designation" x-model="edit.designation" class="input" placeholder="Kassir, ofisiant, aşpaz..."></div>
                <div><label class="label">Parol <span x-show="edit.id" class="font-normal text-zinc-400">(dəyişmək üçün)</span></label><input name="password" type="password" class="input" :required="!edit.id" autocomplete="new-password"></div>
                <div><label class="label">Rol</label>
                    <select name="role" x-model="edit.role" class="input"><option value="user">İşçi (icazələrə görə)</option><option value="admin">Admin (tam giriş)</option></select>
                </div>
            </div>
            <div x-show="edit.role === 'user'">
                <p class="label">İcazələr</p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach (\App\Models\User::SCOPES as $key => $label)
                        <label class="flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50"><input type="checkbox" name="scopes[]" value="{{ $key }}" x-model="edit.scopes" class="rounded text-brand-500"> {{ $label }}</label>
                    @endforeach
                </div>
            </div>
            @if ($tables->isNotEmpty())
                <div>
                    <p class="label">Təyin olunmuş masalar <span class="font-normal text-zinc-400">(ofisiant üçün)</span></p>
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
