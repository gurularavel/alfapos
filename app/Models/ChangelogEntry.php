<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Saytdakı "Yeniliklər" bölməsinin bir versiyası (superadmin idarə edir). */
class ChangelogEntry extends Model
{
    /** Sətir növü => [etiket, badge sinfi] */
    public const TYPES = [
        'new' => ['Yeni', 'badge-green'],
        'improved' => ['Təkmilləşdirmə', 'badge-blue'],
        'fixed' => ['Düzəliş', 'badge-amber'],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['released_at' => 'date', 'items' => 'array', 'is_published' => 'boolean'];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->orderByDesc('released_at')->orderByDesc('id');
    }

    /** @return array<string, list<string>> növ => sətirlər (TYPES sırası ilə) */
    public function grouped(): array
    {
        $groups = [];
        foreach (array_keys(self::TYPES) as $type) {
            $lines = collect($this->items)->where('type', $type)->pluck('text')->all();
            if ($lines) {
                $groups[$type] = $lines;
            }
        }

        return $groups;
    }
}
