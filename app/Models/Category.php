<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToTenant;

    protected $table = 'categories';

    protected $guarded = ['id', 'tenant_id'];

    /** Bu kateqoriyanın məhsullarının şöbəsi (bar, mətbəx); boşdursa əsas şöbə. */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
