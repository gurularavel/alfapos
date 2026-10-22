<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Saytın məzmun səhifələri (Haqqımızda, Məxfilik siyasəti, İstifadə şərtləri) — Markdown. */
class SitePage extends Model
{
    public const SLUGS = ['about' => 'Haqqımızda', 'privacy' => 'Məxfilik siyasəti', 'terms' => 'İstifadə şərtləri'];

    protected $guarded = ['id'];

    /**
     * Markdown → HTML. {telefon}, {email} kimi yer tutucular sayt ayarları ilə əvəz olunur;
     * xam HTML qəbul olunmur (yalnız Markdown).
     */
    public static function render(string $markdown): string
    {
        return Str::markdown(self::placeholders($markdown), ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    public const PLACEHOLDERS = [
        '{şirkət}' => 'company_name', '{telefon}' => 'phone', '{şəhər_telefonu}' => 'city_phone',
        '{email}' => 'email', '{voen}' => 'voen', '{ünvan}' => 'address',
    ];

    /** Mətndəki yer tutucuları ({telefon}, {email} ...) sayt ayarları ilə əvəz edir. */
    public static function placeholders(string $text): string
    {
        return strtr($text, array_map(fn ($key) => (string) site($key), self::PLACEHOLDERS));
    }

    /** HTML; "## " başlıqları mündəricat üçün id="b1", "b2"... alır. */
    public function html(): string
    {
        $n = 0;

        return preg_replace_callback('/<h2>/', function () use (&$n) {
            return '<h2 id="b'.(++$n).'">';
        }, self::render($this->body));
    }

    public static function bySlug(string $slug): self
    {
        return static::where('slug', $slug)->firstOrFail();
    }

    /** "## Başlıq" sətirlərindən mündəricat. */
    public function headings(): array
    {
        preg_match_all('/^##\s+(.+)$/m', $this->body, $m);

        return $m[1];
    }
}
