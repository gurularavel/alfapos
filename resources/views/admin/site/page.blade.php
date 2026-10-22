<x-layouts.app :title="$page->title">
<div x-data="{
        body: @js(old('body', $page->body)),
        html: '',
        timer: null,
        refresh() {
            clearTimeout(this.timer);
            this.timer = setTimeout(async () => {
                try { this.html = (await api(@js(route('admin.site.preview')), { method: 'POST', body: { body: this.body } })).html } catch (e) {}
            }, 300);
        },
        wrap(before, after = before) {
            const ta = $refs.ta, s = ta.selectionStart, e = ta.selectionEnd;
            this.body = this.body.slice(0, s) + before + this.body.slice(s, e) + after + this.body.slice(e);
            this.$nextTick(() => { ta.focus(); ta.setSelectionRange(s + before.length, e + before.length) });
        },
     }" x-init="refresh(); $watch('body', () => refresh())">
    <x-page-header :title="(\App\Models\SitePage::SLUGS[$page->slug] ?? $page->slug).' — redaktə'" subtitle="Mətn Markdown formatındadır; sağda saytda necə görünəcəyi göstərilir.">
        <a href="{{ route('admin.site.index', ['tab' => 'pages']) }}" class="btn-secondary">← Səhifələr</a>
        <a href="{{ route(['about' => 'about', 'privacy' => 'privacy', 'terms' => 'terms'][$page->slug]) }}" target="_blank" class="btn-secondary">Saytda bax</a>
    </x-page-header>

    <form method="POST" action="{{ route('admin.site.pages.update', $page->slug) }}" class="space-y-4">
        @csrf @method('PUT')
        <div class="card card-pad grid gap-4 sm:grid-cols-2">
            <div><label class="label">Başlıq *</label><input name="title" value="{{ old('title', $page->title) }}" class="input" required maxlength="200"></div>
            <div><label class="label">Qısa təsvir (axtarış sistemləri üçün)</label><input name="description" value="{{ old('description', $page->description) }}" class="input" maxlength="300"></div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="card flex flex-col overflow-hidden">
                <div class="flex flex-wrap items-center gap-1 border-b border-zinc-100 px-3 py-2">
                    <button type="button" class="btn-ghost btn-sm font-black" @click="wrap('**')" title="Qalın">B</button>
                    <button type="button" class="btn-ghost btn-sm italic" @click="wrap('*')" title="Kursiv">I</button>
                    <button type="button" class="btn-ghost btn-sm" @click="wrap('\n## ', '\n')" title="Bölmə başlığı">H2</button>
                    <button type="button" class="btn-ghost btn-sm" @click="wrap('\n- ', '')" title="Siyahı">• Siyahı</button>
                    <button type="button" class="btn-ghost btn-sm" @click="wrap('[', '](https://)')" title="Link">Link</button>
                    <span class="ml-auto text-xs text-zinc-400">Yer tutucular: {şirkət} {telefon} {şəhər_telefonu} {email} {voen} {ünvan}</span>
                </div>
                <textarea x-ref="ta" name="body" x-model="body" class="min-h-[60vh] flex-1 resize-y p-4 font-mono text-sm leading-relaxed outline-none" required></textarea>
            </div>
            <div class="card card-pad">
                <p class="mb-3 text-xs font-bold tracking-wide text-zinc-400 uppercase">Önizləmə</p>
                <article class="md-content max-h-[70vh] overflow-y-auto" x-html="html"></article>
            </div>
        </div>
        <button class="btn-primary">Yadda saxla</button>
    </form>
</div>
</x-layouts.app>
