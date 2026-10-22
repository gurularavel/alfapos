<x-layouts.app :title="$customer->name">
    <x-page-header :title="$customer->name" :subtitle="$customer->phone.($customer->email ? ' · '.$customer->email : '')">
        <a href="{{ route('customers.index') }}" class="btn-secondary">← Geri</a>
    </x-page-header>
    <div class="grid gap-4 md:grid-cols-3">
        <div class="card card-pad"><p class="text-sm text-zinc-500">Ümumi xərc</p><p class="text-2xl font-black">{{ money($customer->invoices()->where('status', 'paid')->sum('total')) }}</p></div>
        <div class="card card-pad"><p class="text-sm text-zinc-500">Çek sayı</p><p class="text-2xl font-black">{{ $customer->invoices()->where('status', 'paid')->count() }}</p></div>
        <div class="card card-pad"><p class="text-sm text-zinc-500">Doğum tarixi</p><p class="text-2xl font-black">{{ $customer->birth_date?->format('d.m.Y') ?? '—' }}</p></div>
    </div>
    <div class="card mt-4 overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Faktura</th><th>Tarix</th><th>Status</th><th class="text-right">Cəm</th><th></th></tr></thead>
            <tbody>
            @forelse ($customer->invoices as $invoice)
                <tr>
                    <td class="font-bold">{{ $invoice->number }}</td>
                    <td>{{ $invoice->created_at->format('d.m.Y H:i') }}</td>
                    <td>{!! $invoice->status === 'paid' ? '<span class="badge-green">Ödənilib</span>' : '<span class="badge-red">Ləğv</span>' !!}</td>
                    <td class="text-right font-bold">{{ money($invoice->total) }}</td>
                    <td class="text-right"><button class="btn-ghost btn-sm" onclick="printUrl('{{ route('invoices.receipt', $invoice) }}')"><x-icon name="printer" class="size-4" /></button></td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty icon="invoice" title="Hələ alış yoxdur" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
