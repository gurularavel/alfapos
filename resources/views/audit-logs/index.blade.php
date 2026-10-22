<x-layouts.app title="Audit log">
    <x-page-header title="Audit log" subtitle="Faktura, ləğv və status dəyişiklikləri tarixçəsi." />

    <form class="card mb-4 flex gap-2 p-3">
        <select name="action" class="input"><option value="">Bütün əməliyyatlar</option>@foreach ($actions as $a)<option @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select>
        <button class="btn-primary">Filtr</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Tarix</th><th>İstifadəçi</th><th>Əməliyyat</th><th>Obyekt</th><th>Məlumat</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap text-zinc-500">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? 'Sistem / QR' }}</td>
                    <td><span class="badge-gray">{{ $log->action }}</span></td>
                    <td class="text-zinc-500">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</td>
                    <td class="max-w-sm truncate text-xs text-zinc-500">{{ $log->data ? json_encode($log->data, JSON_UNESCAPED_UNICODE) : '' }}</td>
                    <td class="text-xs text-zinc-400">{{ $log->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="history" title="Qeyd yoxdur" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.app>
