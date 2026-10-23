{{-- Cookie bildirişi (ictimai səhifələr, giriş/qeydiyyat, QR menyu). Seçim "cookie_consent" cookie-sində 1 il saxlanılır.
     Pəncərələrin (z-50) altında qalır; aşağıda öz düymələri olan səhifələrdə (QR menyu, ödəniş) yuxarıda göstərilir. --}}
@props(['position' => 'bottom'])
<div x-data="{ show: ! document.cookie.split('; ').includes('cookie_consent=1') }" x-cloak x-show="show" x-transition.opacity
     @class(['no-print fixed inset-x-0 z-40 p-3 sm:p-4', 'bottom-0' => $position === 'bottom', 'top-0' => $position === 'top']) role="dialog" aria-live="polite" aria-label="Cookie bildirişi">
    <div class="mx-auto flex max-w-3xl flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xl sm:flex-row sm:items-center sm:gap-4">
        <p class="flex-1 text-sm text-zinc-600">
            Saytın işləməsi üçün yalnız <b>zəruri cookie-lərdən</b> istifadə edirik: giriş sessiyası, təhlükəsizlik və seçimlərinizin yadda saxlanılması.
            Reklam və izləmə cookie-ləri yoxdur. Ətraflı: <a href="{{ route('cookies') }}" class="font-semibold text-brand-700 underline">Cookie siyasəti</a>.
        </p>
        <button type="button" class="btn-primary shrink-0"
                @click="document.cookie = 'cookie_consent=1; max-age=31536000; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); show = false">Anladım</button>
    </div>
</div>
