<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchApiController extends Controller
{
    public function __construct(private GlobalSearchService $searchService) {}

    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace ?? $user->workspace ?? $user->client?->workspaces()->first();
        if (! $workspace) {
            return response()->json(['success' => false, 'error' => ['code' => 'NO_WORKSPACE', 'message' => 'Workspace context missing.']], 422);
        }

        $query = (string) $request->input('q', '');
        $results = $this->searchService->search($workspace, $query, (int) $request->input('limit', 10));

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }
}
