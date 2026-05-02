<?php

namespace App\Exceptions\OrderImport;

use RuntimeException;

class InvalidShippingExportException extends RuntimeException
{
    public static function missingHeader(string $column): self
    {
        return new self("ShippingExport CSV is missing required header [{$column}].");
    }

    public static function invalidRow(int $rowNumber, string $message): self
    {
        return new self("ShippingExport row {$rowNumber}: {$message}");
    }
}
