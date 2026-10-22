@php($page = \App\Models\SitePage::bySlug('terms'))
<x-layouts.public :title="$page->title" :description="\App\Models\SitePage::placeholders((string) $page->description)">
    <x-legal-page :page="$page" />
</x-layouts.public>
