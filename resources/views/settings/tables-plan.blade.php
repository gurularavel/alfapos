<x-layouts.app title="Zal planı">
<div x-data="planEditor({ tables: @js($tables), saveUrl: @js(route('settings.tables.plan.save')) })" class="flex flex-col gap-4">
    <x-page-header title="Zal planı" subtitle="Masaları barmaqla və ya siçanla sürüşdürüb zalın real düzülüşünə uyğun yerləşdirin.">
        <a href="{{ route('settings.tables.index') }}" class="btn-secondary">← Masalar</a>
        <button class="btn-secondary" @click="resetFloor()">Bu mərtəbəni avtomatik düz</button>
        <button class="btn-primary" :disabled="!dirty || saving" @click="save()"><x-icon name="check" class="size-4" /> <span x-text="dirty ? 'Yadda saxla' : 'Yadda saxlanılıb'"></span></button>
    </x-page-header>

    <div class="flex flex-wrap gap-2">
        <template x-for="f in floors" :key="f">
            <button class="chip px-5 py-2 text-base" :class="floor === f && 'active'" @click="floor = f" x-text="f"></button>
        </template>
    </div>

    {{-- Kətan: zal (16:10). Masalar mərkəzindən yerləşdirilir, mövqe faizlə saxlanılır --}}
    <div x-ref="canvas" class="relative aspect-[16/10] w-full overflow-hidden rounded-3xl border-2 border-dashed border-zinc-300 bg-[radial-gradient(circle,#d4d4d8_1px,transparent_1px)] [background-size:24px_24px] select-none"
         @pointermove="move($event)" @pointerup="end()" @pointercancel="end()" @pointerleave="end()">
        <template x-for="t in floorTables" :key="t.id">
            <div class="absolute -translate-x-1/2 -translate-y-1/2 cursor-grab touch-none active:cursor-grabbing"
                 :class="dragging === t ? 'z-10 scale-105 opacity-90' : ''"
                 :style="`left: ${t.x}%; top: ${t.y}%`"
                 @pointerdown.prevent="start($event, t)">
                <x-table-tile />
                <span x-show="t.deposit > 0" class="badge-amber absolute -top-2 -right-2 pointer-events-none" x-text="'D'"></span>
            </div>
        </template>
        <p x-show="!floorTables.length" class="absolute inset-0 flex items-center justify-center text-zinc-500">Bu mərtəbədə masa yoxdur.</p>
    </div>
    <p class="text-sm text-zinc-500">İpucu: mərtəbələr masanın "Mərtəbə / zona" sahəsinə görə ayrılır. Yerləşdirilməmiş masalar avtomatik şəbəkədə göstərilir. POS-dakı "Masalar" ekranı bu planı istifadə edir.</p>
</div>
</x-layouts.app>
