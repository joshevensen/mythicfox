<?php

namespace App\Services\SellerStats;

use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Parses a rendered TCGPlayer seller storefront page into rating, review
 * count, and feedback comments. Selectors are isolated here so a TCGPlayer
 * redesign only requires updating this class.
 *
 * The fixture at tests/fixtures/tcgplayer-storefront.html reflects the
 * current expected markup structure.
 */
class TcgplayerStorefrontParser
{
    public function parse(string $html): StorefrontResult
    {
        $doc = new DOMDocument;
        @$doc->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($doc);

        $rating = $this->extractRating($xpath);
        $reviewCount = $this->extractReviewCount($xpath);
        $feedback = $this->extractFeedback($xpath);

        return new StorefrontResult(
            rating: $rating,
            reviewCount: $reviewCount,
            feedback: $feedback,
        );
    }

    private function extractRating(DOMXPath $xpath): ?float
    {
        $node = $xpath->query("//*[contains(@class, 'seller-rating__average')]")->item(0);

        if ($node === null) {
            return null;
        }

        $text = trim($node->textContent);

        if ($text === '' || ! is_numeric($text)) {
            return null;
        }

        return (float) $text;
    }

    private function extractReviewCount(DOMXPath $xpath): ?int
    {
        $node = $xpath->query("//*[contains(@class, 'seller-rating__count')]")->item(0);

        if ($node === null) {
            return null;
        }

        $text = trim($node->textContent);
        preg_match('/[\d,]+/', $text, $matches);

        if (empty($matches)) {
            return null;
        }

        return (int) str_replace(',', '', $matches[0]);
    }

    /**
     * @return array<int, array{text: string, rating: int|null, author: string|null, date: string|null}>|null
     */
    private function extractFeedback(DOMXPath $xpath): ?array
    {
        $items = $xpath->query("//*[contains(@class, 'feedback-item')]");

        if ($items === false || $items->length === 0) {
            return null;
        }

        $feedback = [];

        foreach ($items as $item) {
            $text = $this->childText($xpath, $item, 'feedback-item__comment');

            if ($text === null) {
                continue;
            }

            $ratingText = $this->childText($xpath, $item, 'feedback-item__rating');
            $feedback[] = [
                'text' => $text,
                'rating' => $ratingText !== null ? (int) $ratingText : null,
                'author' => $this->childText($xpath, $item, 'feedback-item__buyer'),
                'date' => $this->childText($xpath, $item, 'feedback-item__date'),
            ];
        }

        return $feedback !== [] ? $feedback : null;
    }

    private function childText(DOMXPath $xpath, DOMNode $context, string $class): ?string
    {
        $node = $xpath->query(".//*[contains(@class, '{$class}')]", $context)->item(0);

        if ($node === null) {
            return null;
        }

        $text = trim($node->textContent);

        return $text !== '' ? $text : null;
    }
}
