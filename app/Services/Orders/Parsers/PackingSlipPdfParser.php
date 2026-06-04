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
 * Expected line-item format (one or two visual lines):
 *   {qty}  ProductLine - Set - ProductName - #Number - Rarity - Condition  $unit  $total
 *
 * When a description wraps, the continuation appears on the very next line,
 * indented more deeply with no price columns.
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
        if ($this->findPdftotext() === null) {
            throw new RuntimeException('pdftotext is not installed. Run `apt-get install poppler-utils` on the server to process packing slip PDFs.');
        }

        if (! is_readable($absolutePath)) {
            throw new RuntimeException("Cannot read PDF at [{$absolutePath}]");
        }

        $process = new Process(['pdftotext', '-layout', $absolutePath, '-']);
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
        $lines = explode("\n", $text);
        $results = collect();
        $currentOrderNumber = null;
        $validated = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            // Detect "Order Number: XXX" (appears at top and bottom of each page).
            if (preg_match('/Order\s+Number:\s+([A-Z0-9][A-Z0-9-]+)/i', $line, $m)) {
                $orderNumber = strtoupper(preg_replace('/[^A-Z0-9-]/', '', $m[1]));
                if (! isset($validated[$orderNumber])) {
                    $this->sellerIdValidator->assertValid($orderNumber);
                    $validated[$orderNumber] = true;
                }
                $currentOrderNumber = $orderNumber;

                continue;
            }

            if ($currentOrderNumber === null) {
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

            $parsed = $this->parseDescription(trim($description), $currentOrderNumber, $quantity, $unitCents, $totalCents);
            if ($parsed !== null) {
                $results->push($parsed);
            }
        }

        return $results;
    }

    private function parseDescription(
        string $description,
        string $orderNumber,
        int $quantity,
        int $unitCents,
        int $totalCents,
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
        );
    }

    protected function findPdftotext(): ?string
    {
        return (new ExecutableFinder)->find('pdftotext');
    }
}
