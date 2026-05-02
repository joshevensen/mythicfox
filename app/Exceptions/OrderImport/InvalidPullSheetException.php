<?php

namespace App\Exceptions\OrderImport;

use RuntimeException;

class InvalidPullSheetException extends RuntimeException
{
    public static function missingHeader(string $column): self
    {
        return new self("PullSheet CSV is missing required header [{$column}].");
    }

    public static function invalidOrderQuantity(int $rowNumber, string $cellValue): self
    {
        return new self("PullSheet row {$rowNumber}: invalid Order Quantity [{$cellValue}]");
    }

    public static function invalidRow(int $rowNumber, string $message): self
    {
        return new self("PullSheet row {$rowNumber}: {$message}");
    }
}
