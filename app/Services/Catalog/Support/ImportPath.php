<?php

namespace App\Services\Catalog\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ImportPath
{
    public static function for(string $purpose, string $originalFilename): string
    {
        $now = Carbon::now();
        $slug = Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME)) ?: 'upload';
        $ext = pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'csv';

        return sprintf(
            'imports/%s/%s/%s/%s-%s.%s',
            $purpose,
            $now->format('Y'),
            $now->format('m'),
            (string) Str::ulid(),
            $slug,
            $ext,
        );
    }
}
