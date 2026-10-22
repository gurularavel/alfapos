<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('app:import-menu-images {dir : credits.json və şəkillərin olduğu qovluq} {--tenant=Demo Restoran : Biznesin adı}')]
#[Description('Qovluqdakı şəkilləri (credits.json: məhsul adı => fayl, müəllif, lisenziya) adı uyğun gələn menyu məhsullarına yükləyir')]
class ImportMenuImages extends Command
{
    public function handle(Tenancy $tenancy): int
    {
        $dir = rtrim($this->argument('dir'), '/\\');
        $credits = json_decode((string) @file_get_contents("$dir/credits.json"), true);
        if (! is_array($credits)) {
            $this->error("$dir/credits.json tapılmadı və ya oxunmur.");

            return self::FAILURE;
        }

        $tenant = Tenant::where('name', $this->option('tenant'))->first();
        if (! $tenant) {
            $this->error('Biznes tapılmadı: '.$this->option('tenant'));

            return self::FAILURE;
        }
        $tenancy->set($tenant);

        $rows = [];
        foreach ($credits as $title => $meta) {
            $item = MenuItem::where('title', $title)->first();
            $path = "$dir/{$meta['file']}";
            if (! $item || ! is_file($path)) {
                $rows[] = [$title, $item ? 'fayl yoxdur' : 'məhsul yoxdur'];

                continue;
            }

            if ($item->image) {
                Storage::disk('public')->delete($item->image);
            }
            $stored = "tenants/{$tenant->id}/menu/".Str::slug($title).'-'.Str::lower(Str::random(6)).'.jpg';
            Storage::disk('public')->put($stored, file_get_contents($path));

            $item->update(['image' => $stored, 'image_credit' => $this->credit($meta)]);
            $rows[] = [$title, 'OK'];
        }

        $this->table(['Məhsul', 'Nəticə'], $rows);

        return self::SUCCESS;
    }

    protected function credit(array $meta): ?string
    {
        $license = $meta['license'] ?? '';
        $artist = trim(preg_replace('/\s+/', ' ', $meta['artist'] ?? ''));

        return Str::limit(implode(' · ', array_filter([
            $artist ?: null,
            $license ?: null,
            $meta['source'] ?? null,
        ])), 497);
    }
}
