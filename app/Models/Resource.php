<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'disk_mb' => 'integer', 'revision' => 'integer'];
    }
}
