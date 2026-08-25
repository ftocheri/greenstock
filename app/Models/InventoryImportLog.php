<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryImportLog extends Model
{
    protected $fillable = ['filename', 'rows_processed', 'rows_skipped', 'ran_at'];

    protected $casts = [
        'ran_at' => 'datetime',
    ];
}
