<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Saytın əlaqə və rekvizit ayarları (açar => dəyər), superadmin redaktə edir. */
class SiteSetting extends Model
{
    public const CACHE_KEY = 'site-settings';

    /** Açar => [etiket, default]. Default-lar ilk quraşdırma üçündür. */
    public const FIELDS = [
        'company_name' => ['Şirkətin adı', 'Kvadrat'],
        'phone' => ['Mobil telefon', '055 703 80 08'],
        'city_phone' => ['Şəhər telefonu', '012 310 10 08'],
        'email' => ['E-poçt', 'support@kvadrat.az'],
        'whatsapp' => ['WhatsApp nömrəsi', '055 703 80 08'],
        'voen' => ['VÖEN', '2701395461'],
        'address' => ['Ünvan', 'Həsən bəy prospekti 78, Bakı'],
        'map_url' => ['Xəritə linki', 'https://maps.app.goo.gl/zq4EyWu9hUAHm3EMA'],
        'map_lat' => ['Xəritə: enlik', '40.3938117'],
        'map_lng' => ['Xəritə: uzunluq', '49.8074573'],
        'work_hours' => ['İş saatları', 'B.e. – Şənbə, 09:00 – 19:00'],
    ];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** Bütün ayarlar (default-larla birləşdirilmiş), keşlənir. */
    public static function values(): array
    {
        $defaults = array_map(fn ($f) => $f[1], self::FIELDS);

        return Cache::rememberForever(self::CACHE_KEY, function () use ($defaults) {
            // Miqrasiyadan əvvəl (məs. ilk quraşdırma) cədvəl olmaya bilər
            if (! Schema::hasTable('site_settings')) {
                return $defaults;
            }

            return array_merge($defaults, static::pluck('value', 'key')->filter(fn ($v) => $v !== null)->all());
        });
    }

    /** Telefonu beynəlxalq formata çevirir: "055 703 80 08" → "+994557038008". */
    public static function intl(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($digits, '994')) {
            return '+'.$digits;
        }

        return '+994'.ltrim($digits, '0');
    }
}
