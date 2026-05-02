<?php

namespace App\Services\Orders\Parsers;

use App\Exceptions\OrderImport\InvalidPullSheetException;
use App\Services\Orders\SellerIdValidator;
use Illuminate\Support\Collection;
use RuntimeException;

class PullSheetParser
{
    private readonly SellerIdValidator $sellerIdValidator;

    public function __construct(?SellerIdValidator $sellerIdValidator = null)
    {
        $this->sellerIdValidator = $sellerIdValidator ?? new SellerIdValidator;
    }

    private const RequiredHeaders = [
        'Product Line',
        'Product Name',
        'Condition',
        'Number',
        'Set',
        'Rarity',
        'SkuId',
        'Order Quantity',
    ];

    /**
     * @return Collection<int, PullSheetLineItem>
     */
    public function parse(string $absolutePath): Collection
    {
        $handle = @fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open PullSheet CSV at [{$absolutePath}]");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new InvalidPullSheetException('PullSheet CSV is empty.');
            }

            foreach (self::RequiredHeaders as $required) {
                if (! in_array($required, $header, true)) {
                    throw InvalidPullSheetException::missingHeader($required);
                }
            }

            $items = collect();
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

                foreach ($this->splitOrderQuantity($row['Order Quantity'] ?? '', $rowNumber) as [$orderNumber, $quantity]) {
                    $canonical = strtoupper($orderNumber);
                    $this->sellerIdValidator->assertValid($canonical);
                    $items->push(new PullSheetLineItem(
                        tcgplayerOrderNumber: $canonical,
                        quantity: $quantity,
                        productLine: trim((string) ($row['Product Line'] ?? '')),
                        setName: trim((string) ($row['Set'] ?? '')),
                        productName: trim((string) ($row['Product Name'] ?? '')),
                        number: trim((string) ($row['Number'] ?? '')),
                        rarity: trim((string) ($row['Rarity'] ?? '')),
                        condition: trim((string) ($row['Condition'] ?? '')),
                        tcgplayerSkuId: $this->parseSkuId($row['SkuId'] ?? null),
                    ));
                }
            }

            return $items;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<int, array{0: string, 1: int}>
     */
    private function splitOrderQuantity(string $cell, int $rowNumber): array
    {
        $cell = trim($cell);
        if ($cell === '') {
            throw InvalidPullSheetException::invalidOrderQuantity($rowNumber, '(empty)');
        }

        // Split on `|` with optional surrounding whitespace; tolerates `A:1|B:2` and `A:1 | B:2`.
        $segments = preg_split('/\s*\|\s*/', $cell) ?: [];
        $pairs = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $parts = explode(':', $segment, 2);
            if (count($parts) !== 2) {
                throw InvalidPullSheetException::invalidOrderQuantity($rowNumber, $cell);
            }

            $orderNumber = trim($parts[0]);
            $qtyRaw = trim($parts[1]);

            if ($orderNumber === '' || ! ctype_digit($qtyRaw)) {
                throw InvalidPullSheetException::invalidOrderQuantity($rowNumber, $cell);
            }

            $pairs[] = [$orderNumber, (int) $qtyRaw];
        }

        if ($pairs === []) {
            throw InvalidPullSheetException::invalidOrderQuantity($rowNumber, $cell);
        }

        return $pairs;
    }

    private function parseSkuId(?string $raw): ?int
    {
        $clean = trim((string) $raw);

        return $clean === '' ? null : (int) $clean;
    }
}
