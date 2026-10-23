<x-layouts.pay :tenant="$tenant" :table="$table" title="Ödənişin nəticəsi">
    <div class="py-10 text-center">
        @if ($payment->status === 'paid')
            <div class="mx-auto flex size-20 items-center justify-center rounded-full bg-brand-50 text-brand-600"><x-icon name="check" class="size-10" /></div>
            <p class="mt-4 text-2xl font-black">Ödəniş uğurla tamamlandı</p>
            <p class="mt-1 text-zinc-500">{{ money($payment->amount) }}. Təşəkkür edirik, yenə gözləyirik!</p>
        @elseif ($failed)
            <div class="mx-auto flex size-20 items-center justify-center rounded-full bg-red-50 text-red-600"><x-icon name="x" class="size-10" /></div>
            <p class="mt-4 text-2xl font-black">Ödəniş tamamlanmadı</p>
            <p class="mt-1 text-zinc-500">Kartdan pul çıxılmayıb. Yenidən cəhd edə və ya kassada ödəyə bilərsiniz.</p>
            @if ($table)
                <a href="{{ route('pay.show', [$tenant->qr_code, $table->qr_token]) }}" class="btn-primary mt-6">Yenidən cəhd et</a>
            @endif
        @elseif ($payment->status === 'cancelled')
            <p class="text-xl font-black">Bu hesab artıq bağlanıb</p>
            <p class="mt-1 text-zinc-500">Sualınız varsa, kassirə müraciət edin.</p>
        @else
            {{-- Bank hələ yekun status verməyib: bir neçə saniyədən bir yenidən yoxlanılır --}}
            <div x-data x-init="setTimeout(() => location.reload(), 4000)">
                <div class="mx-auto size-14 animate-spin rounded-full border-4 border-brand-200 border-t-brand-600"></div>
                <p class="mt-4 text-xl font-black">Ödəniş yoxlanılır…</p>
                <p class="mt-1 text-zinc-500">Bu səhifəni bağlamayın.</p>
            </div>
        @endif
    </div>
</x-layouts.pay>
