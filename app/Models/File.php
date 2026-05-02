<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'file_path', 'original_filename', 'uploaded_at', 'expired_at'])]
class File extends Model
{
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }
}
