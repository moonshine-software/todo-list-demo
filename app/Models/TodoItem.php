<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TodoItem extends Model
{
    protected $fillable = [
        'title',
        'description',
        'from',
        'to',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'from' => 'timestamp',
            'to' => 'timestamp',
            'sort' => 'integer',
        ];
    }
}
