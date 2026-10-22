<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use BelongsToTenant;

    protected $table = 'taxes';

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2'];
    }
}
