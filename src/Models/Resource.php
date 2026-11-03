<?php

namespace QOpenPanel\Community\Models;

use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    protected $table = 'qopenpanel_resources';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'disk_mb' => 'integer', 'revision' => 'integer'];
    }
}
