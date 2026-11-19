<?php

namespace App\Services\Orders\Parsers;

use App\Services\Orders\SellerIdValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Extracts line items from TCGPlayer packing-slip PDFs using pdftotext -layout.
 * The -layout flag preserves column alignment so buyer address text (right column)
 * never bleeds into item descriptions (left column).
 *
 * Three layouts are supported:
 *
 * - Original: an "Order Number: XXX" label at the top and bottom of every page, and
 *   same-line items (one or two visual lines):
 *     {qty}  ProductLine - Set - ProductName - #Number - Rarity - Condition  $unit  $total
 *   When a description wraps, the continuation appears on the very next line,
 *   indented more deeply with no price columns.
 * - Nov 7 2026 (IMb): as the original, plus a USPS Intelligent Mail barcode (IMb)
 *   rendered as a line of exactly 65 F/A/D/T characters.
 * - Nov 13 2026: the label appears only in the page footer, below the barcode and
 *   items, and each item spans three or more lines: description fragment(s) above
 *   a bare "{qty}  $unit  $total" row and the rest of the description below it.
 *   TCGPlayer vertically centers that row, so at most as many lines are taken
 *   below it as were found above it (unlimited when none were above).
 *
 * The pdftotext output is processed page by page (split on form feeds). A page with
 * exactly one distinct labeled order number is attributed to that order from its
 * first line; a page with none yields nothing; a page with several only switches
 * order at each label. Within an order the barcode is attached to every line item
 * that follows it, carries over to the order's next page, and resets when the
 * order changes. Seller validation runs once per distinct order number.
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
        $pdftotext = $this->findPdftotext();
        if ($pdftotext === null) {
            throw new RuntimeException('pdftotext is not installed. Run `apt-get install poppler-utils` on the server to process packing slip PDFs.');
        }

        if (! is_readable($absolutePath)) {
            throw new RuntimeException("Cannot read PDF at [{$absolutePath}]");
        }

        $process = new Process([$pdftotext, '-layout', $absolutePath, '-']);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('pdftotext failed: '.$process->getErrorOutput());
        }

        return $this->parseText($process->getOutput());
    }

    /**
     * @return Collection<int, PackingSlipLine>
     */
    private function parseText(string $text): Collection
    {
        $results = collect();
        $currentOrderNumber = null;
        $currentImbBarcode = null;
        $validated = [];

        foreach (explode("\f", $text) as $page) {
            $lines = explode("\n", $page);
            $count = count($lines);
            $lastConsumedIndex = -1;

            // Resolve the page's order up front when it carries exactly one distinct
            // labeled order number, so lines above a footer-only label are attributed.
            $labeled = $this->distinctLabeledOrderNumbers($lines);
            if (count($labeled) === 1) {
                $orderNumber = $this->resolveOrderNumber($labeled[0], $validated);
                if ($orderNumber !== $currentOrderNumber) {
                    $currentImbBarcode = null;
                }
                $currentOrderNumber = $orderNumber;
            } else {
                $currentOrderNumber = null;
                $currentImbBarcode = null;
            }

            for ($i = 0; $i < $count; $i++) {
                $line = $lines[$i];

                // Detect "Order Number: XXX" (header and/or footer of each page).
                $labeledOrderNumber = $this->labeledOrderNumber($line);
                if ($labeledOrderNumber !== null) {
                    $orderNumber = $this->resolveOrderNumber($labeledOrderNumber, $validated);
                    if ($orderNumber !== $currentOrderNumber) {
                        $currentImbBarcode = null;
                    }
                    $currentOrderNumber = $orderNumber;

                    continue;
                }

                if ($currentOrderNumber === null) {
                    continue;
                }

                // USPS Intelligent Mail barcode: a line of exactly 65 F/A/D/T characters.
                if (preg_match('/^\s*([FADT]{65})\s*$/', $line, $m)) {
                    $currentImbBarcode = $m[1];

                    continue;
                }

                // Three-line item: a bare qty + two prices row with the description split
                // above and below it. Must run before the same-line path, which also
                // matches this row (its lazy description capture takes a single space).
                if (preg_match('/^\s{1,8}(\d+)\s+\$(\d+\.\d{2})\s+\$(\d+\.\d{2})\s*$/', $line, $m)) {
                    $above = [];
                    for ($j = $i - 1; $j > $lastConsumedIndex && $this->isDescriptionLine($lines[$j]); $j--) {
                        array_unshift($above, $lines[$j]);
                    }

                    $below = [];
                    $belowLimit = count($above) > 0 ? count($above) : PHP_INT_MAX;
                    for ($k = $i + 1; $k < $count && count($below) < $belowLimit && $this->isDescriptionLine($lines[$k]); $k++) {
                        $below[] = $lines[$k];
                    }

                    $i += count($below);
                    $lastConsumedIndex = $i;

                    $description = implode(' ', array_map('trim', [...$above, ...$below]));
                    if ($description === '') {
                        Log::warning("PackingSlipPdfParser: price row without description for order {$currentOrderNumber}: ".trim($line));

                        continue;
                    }

                    $parsed = $this->parseDescription(
                        $description,
                        $currentOrderNumber,
                        (int) $m[1],
                        (int) round(((float) $m[2]) * 100),
                        (int) round(((float) $m[3]) * 100),
                        $currentImbBarcode,
                    );
                    if ($parsed !== null) {
                        $results->push($parsed);
                    }

                    continue;
                }

                // Primary line items: leading spaces (1–8) + qty (digits) + description + two prices.
                if (! preg_match('/^\s{1,8}(\d+)\s+(.+?)\s+\$(\d+\.\d{2})\s+\$(\d+\.\d{2})\s*$/', $line, $m)) {
                    continue;
                }

                $quantity = (int) $m[1];
                $description = $m[2];
                $unitCents = (int) round(((float) $m[3]) * 100);
                $totalCents = (int) round(((float) $m[4]) * 100);

                // Skip the per-order "1  Total  $X.XX" summary line.
                if (trim($description) === 'Total') {
                    continue;
                }

                // Continuation: a non-empty next line that has deep indentation,
                // no price columns, and is not itself a primary line item.
                if (isset($lines[$i + 1])) {
                    $next = $lines[$i + 1];
                    if (
                        trim($next) !== ''
                        && preg_match('/^\s{5,}/', $next)
                        && ! str_contains($next, '$')
                        && ! preg_match('/^\s{1,8}\d+\s+/', $next)
                    ) {
                        $description .= ' '.trim($next);
                        $i++;
                    }
                }

                $lastConsumedIndex = $i;

                $parsed = $this->parseDescription(trim($description), $currentOrderNumber, $quantity, $unitCents, $totalCents, $currentImbBarcode);
                if ($parsed !== null) {
                    $results->push($parsed);
                }
            }
        }

        return $results;
    }

    /**
     * Distinct normalized labeled order numbers on a page, in order of appearance.
     *
     * @param  list<string>  $pageLines
     * @return list<string>
     */
    private function distinctLabeledOrderNumbers(array $pageLines): array
    {
        $orderNumbers = [];
        foreach ($pageLines as $line) {
            $orderNumber = $this->labeledOrderNumber($line);
            if ($orderNumber !== null && ! in_array($orderNumber, $orderNumbers, true)) {
                $orderNumbers[] = $orderNumber;
            }
        }

        return $orderNumbers;
    }

    /**
     * The normalized order number from an "Order Number: XXX" line, or null.
     */
    private function labeledOrderNumber(string $line): ?string
    {
        if (! preg_match('/Order\s+Number:\s+([A-Z0-9][A-Z0-9-]+)/i', $line, $m)) {
            return null;
        }

        return strtoupper(preg_replace('/[^A-Z0-9-]/', '', $m[1]));
    }

    /**
     * Validates the seller prefix the first time an order number is seen and returns it.
     *
     * @param  array<string, true>  $validated
     */
    private function resolveOrderNumber(string $orderNumber, array &$validated): string
    {
        if (! isset($validated[$orderNumber])) {
            $this->sellerIdValidator->assertValid($orderNumber);
            $validated[$orderNumber] = true;
        }

        return $orderNumber;
    }

    /**
     * A deep-indented, non-blank line that carries no prices, qty, barcode, or order label:
     * one fragment of a three-line item's description.
     */
    private function isDescriptionLine(string $line): bool
    {
        return trim($line) !== ''
            && preg_match('/^\s{5,}\S/', $line) === 1
            && ! str_contains($line, '$')
            && preg_match('/^\s{1,8}\d+\s/', $line) !== 1
            && preg_match('/^\s*[FADT]{65}\s*$/', $line) !== 1
            && preg_match('/Order\s+Number:/i', $line) !== 1;
    }

    private function parseDescription(
        string $description,
        string $orderNumber,
        int $quantity,
        int $unitCents,
        int $totalCents,
        ?string $imbBarcode,
    ): ?PackingSlipLine {
        // Anchor on `#<Number>`. The number capture allows `//` for double-sided tokens.
        if (! preg_match('/^(.+?)\s+-\s+#([\w\/]+(?:\s*\/\/\s*[\w\/]+)*)\s+-\s+(.+)$/', $description, $parts)) {
            // Lenient fallback: number found but rarity/condition missing (e.g. wrapped off-page).
            if (preg_match('/^(.+?)\s+-\s+#([\w\/]+(?:\s*\/\/\s*[\w\/]+)*)/', $description, $lenient)) {
                $before = preg_split('/\s+-\s+/', trim($lenient[1])) ?: [];
                if (count($before) >= 3) {
                    $productLine = array_shift($before);
                    $setName = array_shift($before);

                    return new PackingSlipLine(
                        tcgplayerOrderNumber: $orderNumber,
                        quantity: $quantity,
                        productLine: trim($productLine),
                        setName: trim($setName),
                        productName: trim(implode(' - ', $before)),
                        number: trim($lenient[2]),
                        rarity: '',
                        condition: '',
                        unitPrice: $unitCents,
                        totalPrice: $totalCents,
                        imbBarcode: $imbBarcode,
                    );
                }
            }

            Log::warning("PackingSlipPdfParser: description does not match expected format: {$description}");

            return null;
        }

        $beforeSegments = preg_split('/\s+-\s+/', trim($parts[1])) ?: [];
        if (count($beforeSegments) < 3) {
            Log::warning("PackingSlipPdfParser: expected ProductLine - Set - ProductName: {$parts[1]}");

            return null;
        }

        $productLine = array_shift($beforeSegments);
        $setName = array_shift($beforeSegments);
        $productName = implode(' - ', $beforeSegments);

        $afterParts = preg_split('/\s+-\s+/', trim($parts[3]), 2) ?: [];
        if (count($afterParts) !== 2) {
            $singleRarity = trim($afterParts[0] ?? '');
            if (preg_match('/^(Common|Uncommon|Rare|Mythic\s+Rare|Super\s+Rare|Majestic|Legendary|Fabled|Enchanted|Token|[MCLRUF])$/i', $singleRarity)) {
                return new PackingSlipLine(
                    tcgplayerOrderNumber: $orderNumber,
                    quantity: $quantity,
                    productLine: trim($productLine),
                    setName: trim($setName),
                    productName: trim($productName),
                    number: trim($parts[2]),
                    rarity: $singleRarity,
                    condition: '',
                    unitPrice: $unitCents,
                    totalPrice: $totalCents,
                    imbBarcode: $imbBarcode,
                );
            }

            Log::warning("PackingSlipPdfParser: expected Rarity - Condition: {$parts[3]}");

            return null;
        }

        return new PackingSlipLine(
            tcgplayerOrderNumber: $orderNumber,
            quantity: $quantity,
            productLine: trim($productLine),
            setName: trim($setName),
            productName: trim($productName),
            number: trim($parts[2]),
            rarity: trim($afterParts[0]),
            condition: trim($afterParts[1]),
            unitPrice: $unitCents,
            totalPrice: $totalCents,
            imbBarcode: $imbBarcode,
        );
    }

    protected function findPdftotext(): ?string
    {
        return (new ExecutableFinder)->find('pdftotext');
    }
}
