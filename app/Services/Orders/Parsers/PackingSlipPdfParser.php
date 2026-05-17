<?php

namespace App\Services\Orders\Parsers;

use App\Services\Orders\SellerIdValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Smalot\PdfParser\Parser;

/**
 * PDFs from TCGPlayer extract through smalot/pdfparser as glyphs without
 * inter-word spaces in the default getText() output. This parser reads
 * positioned text via Page::getDataTm() and reconstructs lines by
 * y-grouping + x-sorting, which preserves real spacing.
 *
 * Description format expected:
 *   <ProductLine> - <Set> - <ProductName> - #<Number> - <Rarity> - <Condition>
 *
 * The product name itself can contain ` - ` (e.g. "Calhoun - Marine Sergeant"),
 * so the parser anchors on the `#<Number>` segment: everything before that is
 * ProductLine + Set + ProductName (first two segments + the join of the rest);
 * everything after is Rarity (one segment) + Condition (the remainder, possibly
 * wrapped onto the next visual y-line).
 */
class PackingSlipPdfParser
{
    private readonly SellerIdValidator $sellerIdValidator;

    public function __construct(?SellerIdValidator $sellerIdValidator = null)
    {
        $this->sellerIdValidator = $sellerIdValidator ?? new SellerIdValidator;
    }

    /**
     * @return Collection<int, PackingSlipLine>
     */
    public function parse(string $absolutePath): Collection
    {
        if (! is_readable($absolutePath)) {
            throw new RuntimeException("Cannot read PDF at [{$absolutePath}]");
        }

        $pdf = (new Parser)->parseFile($absolutePath);
        $lines = collect();

        foreach ($pdf->getPages() as $pageIndex => $page) {
            $reconstructed = $this->reconstructLines($page->getDataTm());

            $orderNumber = $this->findOrderNumber($reconstructed);
            if ($orderNumber === null) {
                Log::warning("PackingSlipPdfParser: page {$pageIndex} has no Order Number header; skipping.");

                continue;
            }

            $this->sellerIdValidator->assertValid($orderNumber);

            $lineItemRows = $this->extractLineItems($reconstructed, $pageIndex);

            foreach ($lineItemRows as $lineItem) {
                $parsed = $this->parseLineItem($lineItem, $orderNumber, $pageIndex);
                if ($parsed !== null) {
                    $lines->push($parsed);
                }
            }
        }

        return $lines;
    }

    /**
     * Group dataTm entries by y-coordinate (with ±1.0 tolerance), sort each
     * group by x ascending, and join with single spaces.
     *
     * @param  array<int, array{0: array, 1: string}>  $dataTm
     * @return array<int, array{y: float, text: string}> ordered top-to-bottom
     */
    private function reconstructLines(array $dataTm): array
    {
        $byY = [];
        foreach ($dataTm as $entry) {
            // Skip standalone header/footer label entries so they never
            // contaminate table rows that share the same y-coordinate.
            if ($this->isHeaderLabel($entry[1])) {
                continue;
            }

            $y = round((float) $entry[0][5], 0);
            $byY[(string) $y][] = ['x' => (float) $entry[0][4], 'text' => $entry[1]];
        }

        $lines = [];
        foreach ($byY as $y => $entries) {
            usort($entries, fn ($a, $b) => $a['x'] <=> $b['x']);
            $lines[] = [
                'y' => (float) $y,
                'text' => implode(' ', array_column($entries, 'text')),
            ];
        }

        usort($lines, fn ($a, $b) => $b['y'] <=> $a['y']);

        return $lines;
    }

    /**
     * @param  array<int, array{y: float, text: string}>  $lines
     */
    private function findOrderNumber(array $lines): ?string
    {
        // smalot splits the hex segments across separate dataTm entries, so the
        // reconstructed line looks like "Order Number: 623394E9- 23CAFE- 565FC".
        // Match liberally then strip the inserted spaces.
        foreach ($lines as $line) {
            if (preg_match('/Order\s*Number:\s*([A-Z0-9][A-Z0-9 -]*?)(?:\s{2,}|\s+Page|$)/i', $line['text'], $m)) {
                $cleaned = preg_replace('/\s+/', '', $m[1]);
                if ($cleaned !== null && $cleaned !== '') {
                    return strtoupper($cleaned);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{y: float, text: string}>  $lines
     * @return array<int, array{primary: string, continuation: ?string}>
     */
    private function extractLineItems(array $lines, int $pageIndex): array
    {
        $count = count($lines);
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $text = $lines[$i]['text'];
            if (! preg_match('/^\d+\s+.+\$\d+\.\d{2}\s+\$\d+\.\d{2}$/', $text)) {
                continue;
            }
            // Skip the per-page total line: "1 Total $6.90" has a single price
            // and doesn't match the two-price pattern, so we shouldn't reach
            // here for it — but a defensive check stays cheap.
            if (preg_match('/^\d+\s+Total\s+\$/', $text)) {
                continue;
            }

            $continuation = null;
            // The wrapped condition (or remainder) is the next y-line below,
            // unless that next line itself is another line item or the
            // page-total line.
            if (isset($lines[$i + 1])) {
                $next = $lines[$i + 1]['text'];
                $isAnotherLineItem = (bool) preg_match('/^\d+\s+.+\$\d+\.\d{2}\s+\$\d+\.\d{2}$/', $next);
                $isTotal = (bool) preg_match('/^\d+\s+Total\s+\$/', $next);
                if (! $isAnotherLineItem && ! $isTotal && trim($next) !== '') {
                    // Strip any header labels that leaked into the
                    // continuation via y-coordinate collision.
                    $cleaned = $this->stripHeaderNoise($next);
                    // Heuristic: only treat it as a continuation if it doesn't
                    // contain a price and is reasonably short (a wrapped
                    // condition or product-name fragment).
                    if ($cleaned !== '' && ! str_contains($cleaned, '$') && strlen($cleaned) < 80) {
                        $continuation = $cleaned;
                    }
                }
            }

            $items[] = ['primary' => $text, 'continuation' => $continuation];
        }

        return $items;
    }

    /**
     * @param  array{primary: string, continuation: ?string}  $lineItem
     */
    private function parseLineItem(array $lineItem, string $orderNumber, int $pageIndex): ?PackingSlipLine
    {
        $primary = $lineItem['primary'];
        if (! preg_match('/^(\d+)\s+(.+?)\s+\$(\d+\.\d{2})\s+\$(\d+\.\d{2})$/', $primary, $m)) {
            Log::warning("PackingSlipPdfParser: page {$pageIndex} could not parse line: {$primary}");

            return null;
        }

        $quantity = (int) $m[1];
        $description = trim($m[2]);
        $unitCents = (int) round(((float) $m[3]) * 100);
        $totalCents = (int) round(((float) $m[4]) * 100);

        if ($lineItem['continuation'] !== null) {
            $description .= ' '.$lineItem['continuation'];
        }
        // Collapse any " - - " that resulted from a trailing dangling separator
        // joining the continuation, and any double whitespace.
        $description = preg_replace('/\s+/', ' ', $description) ?? $description;
        $description = preg_replace('/-\s+-/', '-', $description) ?? $description;

        // Strip header/footer text that leaked into the description via
        // y-coordinate collision (e.g. "...card - Shipping Address: Order Date: ...").
        $description = $this->stripHeaderNoise($description);
        if ($description === '') {
            return null;
        }

        // Anchor on `#<number>`.  The number capture allows `//` for
        // double-sided tokens (e.g. "#22 // 6").
        if (! preg_match('/^(.+?)\s+-\s+#([\w\/]+(?:\s*\/\/\s*[\w\/]+)*)\s+-\s+(.+)$/', $description, $parts)) {
            Log::warning("PackingSlipPdfParser: page {$pageIndex} description does not match the 6-segment format: {$description}");

            return null;
        }

        $beforeNumber = trim($parts[1]);
        $number = trim($parts[2]);
        $afterNumber = trim($parts[3]);

        $beforeSegments = preg_split('/\s+-\s+/', $beforeNumber) ?: [];
        if (count($beforeSegments) < 3) {
            Log::warning("PackingSlipPdfParser: page {$pageIndex} expected ProductLine - Set - ProductName: {$beforeNumber}");

            return null;
        }

        $productLine = array_shift($beforeSegments);
        $setName = array_shift($beforeSegments);
        $productName = implode(' - ', $beforeSegments);

        $afterParts = preg_split('/\s+-\s+/', $afterNumber, 2) ?: [];
        if (count($afterParts) !== 2) {
            Log::warning("PackingSlipPdfParser: page {$pageIndex} expected Rarity - Condition: {$afterNumber}");

            return null;
        }

        return new PackingSlipLine(
            tcgplayerOrderNumber: $orderNumber,
            quantity: $quantity,
            productLine: trim($productLine),
            setName: trim($setName),
            productName: trim($productName),
            number: $number,
            rarity: trim($afterParts[0]),
            condition: trim($afterParts[1]),
            unitPrice: $unitCents,
            totalPrice: $totalCents,
        );
    }

    /**
     * Detect standalone header/footer label entries that smalot emits as
     * individual dataTm text fragments (e.g. "Buyer Name:", "Order Date:").
     */
    private function isHeaderLabel(string $text): bool
    {
        $t = trim($text);

        return (bool) preg_match(
            '/^(Shipping Address|Order Date|Shipping Method|Buyer Name|Seller Name|Thank you for buying)\s*:?$/i',
            $t
        );
    }

    /**
     * Strip header/footer label text that leaked into a table row via
     * y-coordinate collision.  Removes from the first recognised marker
     * through the end of the string.
     */
    private function stripHeaderNoise(string $text): string
    {
        $text = preg_replace(
            '/\s*(Shipping Address|Order Date|Shipping Method|Buyer Name|Seller Name)\s*:.*$/i',
            '',
            $text
        ) ?? $text;

        return rtrim(trim($text), " \t\n\r\0\x0B-");
    }
}
