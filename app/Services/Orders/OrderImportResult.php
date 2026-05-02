<?php

namespace App\Services\Orders;

use App\Models\File;

class OrderImportResult
{
    /**
     * @param  list<File>  $files
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $ordersInserted = 0,
        public int $ordersUpdated = 0,
        public int $lineItemsCreated = 0,
        public int $lineItemsUnmatchedToPdf = 0,
        public int $lineItemsUnmatchedToInventory = 0,
        public array $files = [],
        public array $errors = [],
        public array $warnings = [],
    ) {}

    public function summaryLine(): string
    {
        $totalOrders = $this->ordersInserted + $this->ordersUpdated;
        $primary = sprintf(
            'Imported %d order%s (%d new, %d updated).',
            $totalOrders,
            $totalOrders === 1 ? '' : 's',
            $this->ordersInserted,
            $this->ordersUpdated,
        );

        if ($this->lineItemsUnmatchedToInventory > 0) {
            $primary .= sprintf(
                ' %d line item%s couldn\'t be matched to inventory and were not decremented.',
                $this->lineItemsUnmatchedToInventory,
                $this->lineItemsUnmatchedToInventory === 1 ? '' : 's',
            );
        }

        return $primary;
    }
}
