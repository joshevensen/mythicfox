<?php

/**
 * Serves the card-name search behind the top nav dropdown.
 *
 * @context JSON controller returning up to SearchPrintingsQuery::LIMIT printing rows whose card name contains the term, across all products.
 *
 * @gotchas
 * - The term is trimmed by TrimStrings before validation, so a whitespace-padded one-character term fails min length with 422.
 * - The route is registered as a literal path next to catalog/{product}, which is constrained with whereNumber, so the two never collide.
 * - Rows carry ids only; the client builds the card detail URL with Wayfinder.
 *
 * @dependencies
 * - SearchPrintingsQuery — matching, ranking and cap
 */

namespace App\Http\Controllers\Catalog;

use App\Catalog\Queries\SearchPrintingsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogSearchController extends Controller
{
    public function __construct(private readonly SearchPrintingsQuery $search) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:'.SearchPrintingsQuery::MIN_LENGTH, 'max:'.SearchPrintingsQuery::MAX_LENGTH],
        ]);

        return response()->json(['data' => $this->search->search($validated['q'])]);
    }
}
