<x-layouts.app :title="$plan->exists ? $plan->name : 'Yeni plan'">
    <x-page-header :title="$plan->exists ? $plan->name : 'Yeni plan'"><a href="{{ route('admin.plans.index') }}" class="btn-secondary">← Planlar</a></x-page-header>
    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="card card-pad grid max-w-3xl gap-4 sm:grid-cols-3">
        @csrf
        @if ($plan->exists) @method('PUT') @endif
        <div class="sm:col-span-2"><label class="label">Ad *</label><input name="name" value="{{ old('name', $plan->name) }}" class="input" required></div>
        <div><label class="label">Sıra</label><input type="number" name="sort" value="{{ old('sort', $plan->sort) }}" class="input"></div>
        <div class="sm:col-span-3"><label class="label">Təsvir</label><input name="description" value="{{ old('description', $plan->description) }}" class="input"></div>
        <div><label class="label">Qiymət *</label><input type="number" step="0.01" min="0" name="price" value="{{ old('price', $plan->price) }}" class="input" required></div>
        <div><label class="label">Valyuta</label><input name="currency" value="{{ old('currency', $plan->currency) }}" class="input" required></div>
        <div><label class="label">Dövr</label><select name="interval" class="input"><option value="month" @selected($plan->interval === 'month')>Aylıq</option><option value="year" @selected($plan->interval === 'year')>İllik</option></select></div>
        <div><label class="label">Maks. istifadəçi</label><input type="number" min="1" name="max_users" value="{{ old('max_users', $plan->max_users) }}" class="input" placeholder="limitsiz"></div>
        <div><label class="label">Maks. menyu</label><input type="number" min="1" name="max_menu_items" value="{{ old('max_menu_items', $plan->max_menu_items) }}" class="input" placeholder="limitsiz"></div>
        <div><label class="label">Maks. masa</label><input type="number" min="1" name="max_tables" value="{{ old('max_tables', $plan->max_tables) }}" class="input" placeholder="limitsiz"></div>
        <div class="sm:col-span-3"><label class="label">Xüsusiyyətlər (hər sətirdə bir)</label><textarea name="features" rows="4" class="input">{{ old('features', implode("\n", $plan->features ?? [])) }}</textarea></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active)) class="rounded text-brand-500"> Aktiv (qeydiyyatda görünür)</label>
        <div class="sm:col-span-3"><button class="btn-primary">Yadda saxla</button></div>
    </form>
</x-layouts.app>
