<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\SitePage;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Superadmin: saytın əlaqə məlumatları, məzmun səhifələri və sual-cavab. */
class SiteController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.site.index', [
            'tab' => in_array($request->query('tab'), ['contacts', 'pages', 'faq']) ? $request->query('tab') : 'contacts',
            'values' => SiteSetting::values(),
            'pages' => SitePage::orderBy('id')->get(),
            'faqs' => Faq::orderBy('sort')->orderBy('id')->get(),
            'groups' => Faq::distinct()->orderBy('group')->pluck('group'),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'city_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:120'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'voen' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'map_url' => ['nullable', 'url', 'max:500'],
            'map_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'map_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'work_hours' => ['nullable', 'string', 'max:120'],
        ]);

        foreach ($data as $key => $value) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $value ?? '']);
        }

        return redirect()->route('admin.site.index', ['tab' => 'contacts'])->with('success', 'Əlaqə məlumatları yeniləndi.');
    }

    public function editPage(string $slug)
    {
        return view('admin.site.page', ['page' => SitePage::bySlug($slug)]);
    }

    public function updatePage(Request $request, string $slug)
    {
        SitePage::bySlug($slug)->update($request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:100000'],
        ]));

        return back()->with('success', 'Səhifə yadda saxlanıldı.');
    }

    /** Markdown önizləməsi (redaktorda canlı göstərmək üçün). */
    public function preview(Request $request)
    {
        $request->validate(['body' => ['nullable', 'string', 'max:100000']]);

        return response()->json(['html' => SitePage::render((string) $request->input('body'))]);
    }

    public function storeFaq(Request $request)
    {
        Faq::create($this->faqData($request) + ['sort' => (int) Faq::max('sort') + 1]);

        return redirect()->route('admin.site.index', ['tab' => 'faq'])->with('success', 'Sual əlavə edildi.');
    }

    public function updateFaq(Request $request, Faq $faq)
    {
        $faq->update($this->faqData($request));

        return redirect()->route('admin.site.index', ['tab' => 'faq'])->with('success', 'Sual yeniləndi.');
    }

    public function destroyFaq(Faq $faq)
    {
        $faq->delete();

        return redirect()->route('admin.site.index', ['tab' => 'faq'])->with('success', 'Sual silindi.');
    }

    /** Sualı siyahıda yuxarı/aşağı aparır (qonşusu ilə sırasını dəyişir). */
    public function moveFaq(Request $request, Faq $faq)
    {
        $up = $request->input('dir') === 'up';
        $all = Faq::orderBy('sort')->orderBy('id')->get()->values();
        $i = $all->search(fn ($f) => $f->id === $faq->id);
        $j = $up ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < $all->count()) {
            $all->splice($j, 0, [$all->pull($i)]);
            $all->values()->each(fn ($f, $k) => $f->update(['sort' => $k + 1]));
        }

        return redirect()->route('admin.site.index', ['tab' => 'faq']);
    }

    protected function faqData(Request $request): array
    {
        return $request->validate([
            'group' => ['required', 'string', 'max:60'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
