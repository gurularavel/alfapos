<?php

namespace App\Console\Commands;

use App\Http\Controllers\Settings\TerminalController;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('app:import-screensaver {dir : *.jpg şəkillərin olduğu qovluq} {--tenant=Demo Restoran : Biznesin adı} {--replace : Mövcud ekran qoruyucu şəkillərini sil}')]
#[Description('Qovluqdakı şəkilləri biznesin POS ekran qoruyucusuna (və müştəri ekranına) əlavə edir')]
class ImportScreensaver extends Command
{
    public function handle(): int
    {
        $dir = rtrim($this->argument('dir'), '/\\');
        $files = glob("$dir/*.jpg") ?: [];
        $tenant = Tenant::where('name', $this->option('tenant'))->first();
        if (! $tenant || ! $files) {
            $this->error($tenant ? "$dir qovluğunda şəkil yoxdur." : 'Biznes tapılmadı: '.$this->option('tenant'));

            return self::FAILURE;
        }

        $images = $tenant->terminalSetting('images');
        if ($this->option('replace')) {
            Storage::disk('public')->delete($images);
            $images = [];
        }

        foreach ($files as $file) {
            if (count($images) >= TerminalController::MAX_IMAGES) {
                $this->warn('Maksimum '.TerminalController::MAX_IMAGES.' şəkil — qalanları ötürüldü.');
                break;
            }
            $path = "tenants/{$tenant->id}/screensaver/".pathinfo($file, PATHINFO_FILENAME).'-'.Str::lower(Str::random(6)).'.jpg';
            Storage::disk('public')->put($path, file_get_contents($file));
            $images[] = $path;
        }

        $tenant->update(['terminal_settings' => array_merge(Tenant::TERMINAL_DEFAULTS, $tenant->terminal_settings ?? [], ['images' => $images])]);
        $this->info(count($images).' ekran qoruyucu şəkli aktivdir.');

        return self::SUCCESS;
    }
}
