<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Services\Billing\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanEntitlement
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Admins and superadmins bypass client entitlement checks
        if ($user->role === 'admin' || $user->role === 'superadmin') {
            return $next($request);
        }

        $workspaceId = $user->current_workspace_id ?? $user->workspace_id;
        $workspace = $workspaceId ? Workspace::find($workspaceId) : ($user->workspace ?? $user->ownedWorkspaces()->first());

        if (! EntitlementService::can($workspace, $feature)) {
            $readableFeature = ucwords(str_replace('_', ' ', $feature));
            $reason = "{$readableFeature} is not available on your current plan. Upgrade your plan to activate it.";

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Upgrade Required',
                    'message' => $reason,
                    'feature' => $feature,
                    'upgrade_url' => route('client.pricing'),
                ], 403);
            }

            return redirect()->route('client.pricing')->with([
                'upgrade_required' => true,
                'upgrade_reason' => $reason,
            ]);
        }

        return $next($request);
    }
}
