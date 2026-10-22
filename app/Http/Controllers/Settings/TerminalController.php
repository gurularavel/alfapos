<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** POS terminalı: ekran qoruyucu şəkilləri, avtomatik kilid vaxtı. */
class TerminalController extends Controller
{
    public const MAX_IMAGES = 10;

    public function __construct(protected Tenancy $tenancy) {}

    public function edit()
    {
        $tenant = $this->tenancy->tenant();

        return view('settings.terminal', [
            'store' => $tenant,
            'images' => $tenant->screensaverUrls(),
            'staffWithoutPin' => User::where('tenant_id', $tenant->id)->where('is_active', true)->whereNull('pin_hash')->pluck('name'),
        ]);
    }

    public function update(Request $request)
    {
        $tenant = $this->tenancy->tenant();
        $images = $tenant->terminalSetting('images');

        $data = $request->validate([
            'lock_timeout' => ['required', 'integer', 'min:10', 'max:3600'],
            'images' => ['nullable', 'array', 'max:'.max(0, self::MAX_IMAGES - count($images))],
            'images.*' => ['image', 'max:4096'],
        ], ['images.max' => 'Maksimum '.self::MAX_IMAGES.' şəkil yükləmək olar.']);

        foreach ($request->file('images', []) as $file) {
            $images[] = $file->store("tenants/{$tenant->id}/screensaver", 'public');
        }

        $this->save($tenant, [
            'images' => array_values($images),
            'lock_timeout' => (int) $data['lock_timeout'],
            'lock_after_order' => $request->boolean('lock_after_order'),
            'sound_add_item' => $request->boolean('sound_add_item'),
            'sound_new_order' => $request->boolean('sound_new_order'),
        ]);

        return back()->with('success', 'Terminal ayarları yeniləndi.');
    }

    public function destroyImage(int $index)
    {
        $tenant = $this->tenancy->tenant();
        $images = $tenant->terminalSetting('images');
        abort_unless(isset($images[$index]), 404);

        Storage::disk('public')->delete($images[$index]);
        unset($images[$index]);
        $this->save($tenant, ['images' => array_values($images)]);

        return back()->with('success', 'Şəkil silindi.');
    }

    protected function save(Tenant $tenant, array $changes): void
    {
        $tenant->update(['terminal_settings' => array_merge(Tenant::TERMINAL_DEFAULTS, $tenant->terminal_settings ?? [], $changes)]);
    }
}
