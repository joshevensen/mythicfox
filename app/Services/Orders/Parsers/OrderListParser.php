<?php

namespace App\Services\Orders\Parsers;

use App\Exceptions\OrderImport\InvalidOrderListException;
use App\Services\Orders\SellerIdValidator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

class OrderListParser
{
    private readonly SellerIdValidator $sellerIdValidator;

    public function __construct(?SellerIdValidator $sellerIdValidator = null)
    {
        $this->sellerIdValidator = $sellerIdValidator ?? new SellerIdValidator;
    }

    private const RequiredHeaders = [
        'Order #',
        'Buyer Name',
        'Order Date',
        'Status',
        'Product Amt',
        'Shipping Amt',
        'Total Amt',
        'Buyer Paid',
    ];

    /**
     * @return Collection<int, OrderListRow>
     */
    public function parse(string $absolutePath): Collection
    {
        $handle = @fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open OrderList CSV at [{$absolutePath}]");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new InvalidOrderListException('OrderList CSV is empty.');
            }

            foreach (self::RequiredHeaders as $required) {
                if (! in_array($required, $header, true)) {
                    throw InvalidOrderListException::missingHeader($required);
                }
            }

            $rows = collect();
            $rowNumber = 1;

            while (($cells = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($cells === [null] || $cells === []) {
                    continue;
                }

                // Header declares 10 columns but data rows have 9 — pad to header length.
                while (count($cells) < count($header)) {
                    $cells[] = '';
                }

                $row = array_combine(array_slice($header, 0, count($cells)), $cells);
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
    private function buildRow(array $row, int $rowNumber): OrderListRow
    {
        $orderNumberRaw = trim((string) ($row['Order #'] ?? ''));
        if ($orderNumberRaw === '') {
            throw InvalidOrderListException::invalidRow($rowNumber, 'missing Order #');
        }

        $rawDate = trim((string) ($row['Order Date'] ?? ''));
        try {
            $orderDate = CarbonImmutable::createFromFormat('D, j F Y', $rawDate);
            if ($orderDate === false) {
                throw new \InvalidArgumentException("invalid date [{$rawDate}]");
            }
        } catch (\Throwable $e) {
            throw InvalidOrderListException::invalidRow($rowNumber, "invalid Order Date [{$rawDate}]");
        }

        $buyerPaidRaw = trim((string) ($row['Buyer Paid'] ?? ''));

        $orderNumber = strtoupper($orderNumberRaw);
        $this->sellerIdValidator->assertValid($orderNumber);

        return new OrderListRow(
            tcgplayerOrderNumber: $orderNumber,
            tcgplayerStatus: trim((string) ($row['Status'] ?? '')),
            buyerName: trim((string) ($row['Buyer Name'] ?? '')),
            orderDate: $orderDate->startOfDay(),
            productAmount: $this->parseCents($row['Product Amt'] ?? null, $rowNumber, 'Product Amt'),
            shippingAmount: $this->parseCents($row['Shipping Amt'] ?? null, $rowNumber, 'Shipping Amt'),
            totalAmount: $this->parseCents($row['Total Amt'] ?? null, $rowNumber, 'Total Amt'),
            buyerPaid: strcasecmp($buyerPaidRaw, 'true') === 0,
        );
    }

    private function parseCents(?string $raw, int $rowNumber, string $column): int
    {
        $clean = ltrim(trim((string) $raw), '$');
        if ($clean === '' || ! is_numeric($clean)) {
            throw InvalidOrderListException::invalidRow($rowNumber, "invalid {$column} [{$raw}]");
        }

        return (int) round(((float) $clean) * 100);
    }
}
