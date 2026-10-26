<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Şöbə (mətbəx, bar ...). Məhsul şöbəsi: məhsulda seçilib → kateqoriyanınkı → əsas şöbə (is_default).
 * print_at: pos — çeki kassa çap edir, station — şöbənin öz cihazı (Mətbəx ekranında həmin şöbə seçilib) çap edir.
 */
class Department extends Model
{
    use BelongsToTenant;

    public const PRINT_AT = ['pos' => 'Kassada (POS-un printerində)', 'station' => 'Şöbənin öz cihazında'];

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** Biznesin əsas şöbəsinin id-si (məhsula və kateqoriyaya şöbə verilməyibsə). */
    public static function defaultId(): ?int
    {
        return static::orderByDesc('is_default')->orderBy('sort')->orderBy('id')->value('id');
    }

    /** Məhsulun şöbəsi: məhsulun özü, yoxdursa kateqoriyası, yoxdursa əsas şöbə. */
    public static function forItem(MenuItem $item, ?int $defaultId = null): ?int
    {
        return $item->department_id ?? $item->category?->department_id ?? $defaultId ?? static::defaultId();
    }
}
