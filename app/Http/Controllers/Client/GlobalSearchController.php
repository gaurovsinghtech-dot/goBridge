<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __construct(private GlobalSearchService $searchService) {}

    public function search(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        if (! $workspace) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        $query = (string) $request->input('q', '');
        $results = $this->searchService->search($workspace, $query);

        return response()->json($results);
    }
}
