<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\UserProductTour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductTourController extends Controller
{
    /**
     * Get tour status for the current authenticated user.
     */
    public function status(Request $request, ?string $tourKey = 'dashboard_tour'): JsonResponse
    {
        $user = $request->user();
        $tour = UserProductTour::where('user_id', $user->id)
            ->where('tour_key', $tourKey)
            ->first();

        $isCompleted = (bool) $tour?->isCompleted();
        $isSkipped = (bool) $tour?->isSkipped();

        return response()->json([
            'tour_key' => $tourKey,
            'current_step' => (int) ($tour?->current_step ?? 0),
            'completed_at' => $tour?->completed_at?->toIso8601String(),
            'skipped_at' => $tour?->skipped_at?->toIso8601String(),
            'is_completed' => $isCompleted,
            'is_skipped' => $isSkipped,
            'should_show' => ! $isCompleted && ! $isSkipped,
        ]);
    }

    /**
     * Save progress to a specific step.
     */
    public function progress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tour_key' => ['required', 'string', 'max:64'],
            'step' => ['required', 'integer', 'min:0'],
        ]);

        $tour = UserProductTour::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'tour_key' => $validated['tour_key'],
            ],
            [
                'current_step' => $validated['step'],
            ]
        );

        return response()->json([
            'success' => true,
            'current_step' => $tour->current_step,
        ]);
    }

    /**
     * Mark tour as completed.
     */
    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tour_key' => ['required', 'string', 'max:64'],
        ]);

        $tour = UserProductTour::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'tour_key' => $validated['tour_key'],
            ],
            [
                'completed_at' => now(),
                'skipped_at' => null,
            ]
        );

        return response()->json([
            'success' => true,
            'completed_at' => $tour->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * Skip the tour.
     */
    public function skip(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tour_key' => ['required', 'string', 'max:64'],
        ]);

        $tour = UserProductTour::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'tour_key' => $validated['tour_key'],
            ],
            [
                'skipped_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'skipped_at' => $tour->skipped_at?->toIso8601String(),
        ]);
    }

    /**
     * Reset tour (allows restarting from Settings or Help).
     */
    public function reset(Request $request): JsonResponse
    {
        $tourKey = (string) $request->input('tour_key', 'dashboard_tour');

        $tour = UserProductTour::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'tour_key' => $tourKey,
            ],
            [
                'current_step' => 0,
                'completed_at' => null,
                'skipped_at' => null,
            ]
        );

        return response()->json([
            'success' => true,
            'reset' => true,
            'tour_key' => $tourKey,
            'current_step' => 0,
        ]);
    }
}
