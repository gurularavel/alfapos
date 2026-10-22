<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(Terminal::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(StoreTable::class);
    }
}
