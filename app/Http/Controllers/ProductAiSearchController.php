<?php

namespace App\Http\Controllers;

use App\Exceptions\InventoryQueryTranslationException;
use App\Services\InventoryQueryAssistant;
use Illuminate\Http\Request;

class ProductAiSearchController extends Controller
{
    public function __invoke(Request $request, InventoryQueryAssistant $assistant)
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:200'],
        ]);

        try {
            $filters = $assistant->translate($validated['query']);
        } catch (InventoryQueryTranslationException) {
            // The specific reason is already logged inside the assistant — a user can't act on
            // "which of five things went wrong" anyway, so one generic message covers all of them.
            return response()->json([
                'error' => "Couldn't understand that — try rephrasing, or use the search box below.",
            ], 422);
        }

        return response()->json(['filters' => $filters]);
    }
}
