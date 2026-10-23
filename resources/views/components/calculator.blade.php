{{-- Kassir kalkulyatoru (POS alətlər panelindən açılır). Store: resources/js/calculator.js --}}
<div x-data x-cloak x-show="$store.calc.open" x-transition.opacity.duration.150ms class="no-print fixed inset-0 z-[65] flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-6" @click.self="$store.calc.open = false" role="dialog" aria-label="Kalkulyator">
    <div class="w-full max-w-sm rounded-t-3xl bg-ink p-4 text-white shadow-2xl [touch-action:manipulation] sm:rounded-3xl">
        <div class="mb-3 flex items-center justify-between">
            <p class="font-bold text-white/70">Kalkulyator</p>
            <button type="button" class="rounded-xl px-3 py-1 text-white/70 active:bg-white/20" @click="$store.calc.open = false" aria-label="Bağla">✕</button>
        </div>
        <div class="mb-3 rounded-2xl bg-white/[0.07] px-4 py-3 text-right">
            <p class="h-5 text-sm text-white/50 tabular-nums" x-text="$store.calc.history"></p>
            <p class="truncate text-5xl font-black tabular-nums" x-text="$store.calc.display"></p>
        </div>
        <div class="grid grid-cols-4 gap-2">
            @foreach ([['C', 'clear()', 'text-red-300'], ['±', 'negate()', ''], ['%', 'percent()', ''], ['÷', "operator('÷')", 'op'],
                       ['7', "digit('7')", 'n'], ['8', "digit('8')", 'n'], ['9', "digit('9')", 'n'], ['×', "operator('×')", 'op'],
                       ['4', "digit('4')", 'n'], ['5', "digit('5')", 'n'], ['6', "digit('6')", 'n'], ['−', "operator('−')", 'op'],
                       ['1', "digit('1')", 'n'], ['2', "digit('2')", 'n'], ['3', "digit('3')", 'n'], ['+', "operator('+')", 'op'],
                       ['⌫', 'back()', ''], ['0', "digit('0')", 'n'], ['.', 'dot()', 'n'], ['=', 'equals()', 'eq']] as [$label, $action, $kind])
                <button type="button" @click="$store.calc.{{ $action }}"
                        @class(['rounded-2xl py-4 text-2xl font-bold select-none', 'bg-white/[0.07] active:bg-white/20' => in_array($kind, ['n', '', 'text-red-300']), 'text-red-300' => $kind === 'text-red-300',
                                'bg-white/15 text-brand-200 active:bg-white/30' => $kind === 'op', 'bg-brand-500 active:bg-brand-700' => $kind === 'eq'])
                        @if ($kind === 'op') :class="$store.calc.op === '{{ $label }}' && $store.calc.fresh && 'ring-2 ring-brand-300'" @endif>{{ $label }}</button>
            @endforeach
        </div>
    </div>
</div>
