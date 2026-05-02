<?php

namespace App\Services\Orders\Parsers;

use App\Exceptions\OrderImport\InvalidShippingExportException;
use App\Services\Orders\SellerIdValidator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

class ShippingExportParser
{
    private readonly SellerIdValidator $sellerIdValidator;

    public function __construct(?SellerIdValidator $sellerIdValidator = null)
    {
        $this->sellerIdValidator = $sellerIdValidator ?? new SellerIdValidator;
    }

    private const RequiredHeaders = [
        'Order #',
        'FirstName',
        'LastName',
        'Order Date',
        'Address1',
        'City',
        'State',
        'PostalCode',
        'Country',
    ];

    /**
     * @return Collection<int, ShippingExportRow>
     */
    public function parse(string $absolutePath): Collection
    {
        $handle = @fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open ShippingExport CSV at [{$absolutePath}]");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new InvalidShippingExportException('ShippingExport CSV is empty.');
            }

            foreach (self::RequiredHeaders as $required) {
                if (! in_array($required, $header, true)) {
                    throw InvalidShippingExportException::missingHeader($required);
                }
            }

            $rows = collect();
            $rowNumber = 1;

            while (($cells = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($cells === [null] || $cells === []) {
                    continue;
                }

                $row = array_combine($header, array_pad($cells, count($header), ''));
                if ($row === false) {
                    continue;
                }

                $rows->push($this->buildRow($row, $rowNumber));
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function buildRow(array $row, int $rowNumber): ShippingExportRow
    {
        $orderNumberRaw = trim((string) ($row['Order #'] ?? ''));
        if ($orderNumberRaw === '') {
            throw InvalidShippingExportException::invalidRow($rowNumber, 'missing Order #');
        }

        $rawDate = trim((string) ($row['Order Date'] ?? ''));
        try {
            $orderDate = CarbonImmutable::createFromFormat('Y-m-d', $rawDate);
            if ($orderDate === false) {
                throw new \InvalidArgumentException("invalid date [{$rawDate}]");
            }
        } catch (\Throwable) {
            throw InvalidShippingExportException::invalidRow($rowNumber, "invalid Order Date [{$rawDate}]");
        }

        $itemCountRaw = trim((string) ($row['Item Count'] ?? ''));
        $weightRaw = trim((string) ($row['Product Weight'] ?? ''));

        $orderNumber = strtoupper($orderNumberRaw);
        $this->sellerIdValidator->assertValid($orderNumber);

        return new ShippingExportRow(
            tcgplayerOrderNumber: $orderNumber,
            buyerFirstname: trim((string) ($row['FirstName'] ?? '')),
            buyerLastname: trim((string) ($row['LastName'] ?? '')),
            address1: $this->nullifyEmpty($row['Address1'] ?? null),
            address2: $this->nullifyEmpty($row['Address2'] ?? null),
            city: $this->nullifyEmpty($row['City'] ?? null),
            state: $this->nullifyEmpty($row['State'] ?? null),
            postalCode: $this->nullifyEmpty($row['PostalCode'] ?? null),
            country: $this->nullifyEmpty($row['Country'] ?? null),
            orderDate: $orderDate->startOfDay(),
            shippingMethod: $this->nullifyEmpty($row['Shipping Method'] ?? null),
            itemCount: $itemCountRaw === '' ? null : (int) $itemCountRaw,
            productWeight: $weightRaw === '' ? null : (float) $weightRaw,
            trackingNumber: $this->nullifyEmpty($row['Tracking #'] ?? null),
            carrier: $this->nullifyEmpty($row['Carrier'] ?? null),
        );
    }

    private function nullifyEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
