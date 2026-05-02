<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Generates storage-relative file paths matching the
 * {type}/{purpose}/{YYYY}/{MM}/{ulid}-{slug}.{ext} convention from
 * docs/saas-design.md#path-convention.
 */
class FilePath
{
    public static function build(string $type, string $purpose, string $originalFilename): string
    {
        $now = Carbon::now();
        $slug = Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME)) ?: 'upload';
        $ext = pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'bin';

        return sprintf(
            '%s/%s/%s/%s/%s-%s.%s',
            $type,
            $purpose,
            $now->format('Y'),
            $now->format('m'),
            (string) Str::ulid(),
            $slug,
            $ext,
        );
    }
}
