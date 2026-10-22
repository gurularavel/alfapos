{{--
    Masa — yuxarıdan görünüş. Alpine kontekstində `t` (masa), `chairs(t)` olmalıdır;
    `state` ifadəsi masanın canlı vəziyyətini verir (null = boş).
--}}
@props(['state' => 'null'])
<div class="flex flex-col items-center gap-1.5">
    <div class="flex justify-center gap-2">
        <template x-for="i in chairs(t).top"><span class="h-3.5 w-7 rounded-t-full" :class="{{ $state }} ? 'bg-amber-500' : 'bg-zinc-300'"></span></template>
    </div>
    <div class="flex min-h-20 flex-col items-center justify-center rounded-2xl px-3 py-2 text-center"
         :style="`width: ${Math.max(chairs(t).top, 2) * 36 + 14}px`"
         :class="{{ $state }} ? ({{ $state }}.ready ? 'bg-brand-500 text-white' : 'bg-amber-400 text-ink') : 'border-[3px] border-zinc-300 bg-white'">
        <span class="text-lg leading-tight font-black" x-text="t.title"></span>
        <span class="text-sm font-bold" x-show="{{ $state }}" x-text="money({{ $state }}?.due)"></span>
        <span class="text-xs text-zinc-500" x-show="!{{ $state }}" x-text="t.seats + ' nəfər'"></span>
    </div>
    <div class="flex min-h-3.5 justify-center gap-2">
        <template x-for="i in chairs(t).bottom"><span class="h-3.5 w-7 rounded-b-full" :class="{{ $state }} ? 'bg-amber-500' : 'bg-zinc-300'"></span></template>
    </div>
</div>
