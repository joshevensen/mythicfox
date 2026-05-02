<?php

namespace App\Services\Orders;

final readonly class OrderImportInput
{
    public function __construct(
        public string $orderListPath,
        public ?string $shippingExportPath = null,
        public ?string $pullSheetPath = null,
        public ?string $packingSlipPdfPath = null,
        public ?string $orderListFilename = null,
        public ?string $shippingExportFilename = null,
        public ?string $pullSheetFilename = null,
        public ?string $packingSlipFilename = null,
    ) {}
}
