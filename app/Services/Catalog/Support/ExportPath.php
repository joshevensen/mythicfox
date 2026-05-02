<?php

namespace App\Services\Catalog\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ExportPath
{
    public static function for(string $purpose, string $slug): string
    {
        $now = Carbon::now();

        return sprintf(
            'exports/%s/%s/%s/%s-%s.csv',
            $purpose,
            $now->format('Y'),
            $now->format('m'),
            (string) Str::ulid(),
            $slug,
        );
    }
}
