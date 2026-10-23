<x-layouts.pay :tenant="$tenant" :table="$table">
    @if ($payment)
        @if ($payment->is_test)
            <p class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">Test rejimi — real pul çıxılmır.</p>
        @endif
        <div class="rounded-2xl border border-zinc-200 p-4">
            @foreach ($orders as $order)
                @foreach ($order->items->where('status', '!=', 'cancelled') as $item)
                    <p class="flex justify-between gap-3 py-1 text-sm">
                        <span>{{ $item->quantity }} × {{ $item->title }}@if ($item->variant_title) <span class="text-zinc-500">({{ $item->variant_title }})</span>@endif</span>
                        <span class="shrink-0 font-semibold">{{ money($item->price * $item->quantity) }}</span>
                    </p>
                @endforeach
            @endforeach
            <p class="mt-3 flex justify-between border-t border-zinc-100 pt-3 text-2xl font-black"><span>Ödəniləcək</span><span>{{ money($payment->amount) }}</span></p>
            <p class="mt-1 text-xs text-zinc-500">Vergi, servis haqqı və endirim məbləğə daxildir.</p>
        </div>

        <form method="POST" action="{{ route('pay.checkout', [$tenant->qr_code, $table->qr_token]) }}" class="mt-5" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <button class="btn-primary w-full py-4 text-lg" :disabled="busy">
                <x-icon name="card" class="size-6" /> <span x-text="busy ? 'Bank səhifəsi açılır…' : 'Kartla ödə · {{ money($payment->amount) }}'">Kartla ödə · {{ money($payment->amount) }}</span>
            </button>
        </form>
    @elseif ($paid)
        <div class="py-10 text-center">
            <div class="mx-auto flex size-20 items-center justify-center rounded-full bg-brand-50 text-brand-600"><x-icon name="check" class="size-10" /></div>
            <p class="mt-4 text-2xl font-black">Hesab ödənilib</p>
            <p class="mt-1 text-zinc-500">{{ money($paid->amount) }} · {{ $paid->paid_at?->format('H:i') }}. Təşəkkür edirik!</p>
        </div>
    @else
        <div class="py-10 text-center">
            <div class="mx-auto flex size-20 items-center justify-center rounded-full bg-zinc-100 text-zinc-500"><x-icon name="invoice" class="size-10" /></div>
            <p class="mt-4 text-xl font-black">Hesab hələ göndərilməyib</p>
            <p class="mt-1 text-zinc-500">Ofisiantdan və ya kassirdən hesabı onlayn ödəniş üçün göndərməyi xahiş edin, sonra bu QR-ı yenidən skan edin.</p>
            <a href="{{ request()->url() }}" class="btn-secondary mt-6"><x-icon name="refresh" class="size-4" /> Yenilə</a>
        </div>
    @endif
</x-layouts.pay>
