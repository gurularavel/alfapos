<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use BelongsToTenant;

    protected $table = 'feedbacks';

    protected $guarded = ['id', 'tenant_id'];

}
