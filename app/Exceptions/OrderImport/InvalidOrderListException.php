<?php

namespace App\Exceptions\OrderImport;

use RuntimeException;

class InvalidOrderListException extends RuntimeException
{
    public static function missingHeader(string $column): self
    {
        return new self("OrderList CSV is missing required header [{$column}].");
    }

    public static function invalidRow(int $rowNumber, string $message): self
    {
        return new self("OrderList row {$rowNumber}: {$message}");
    }
}
