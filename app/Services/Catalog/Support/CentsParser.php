<?php

namespace App\Services\Catalog\Support;

class CentsParser
{
    public static function parse(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return (int) round(((float) $trimmed) * 100);
    }
}
