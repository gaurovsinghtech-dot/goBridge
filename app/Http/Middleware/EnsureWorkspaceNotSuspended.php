<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Allow super admin impersonator to inspect suspended workspaces
        if ($request->session()->get('impersonating')) {
            return $next($request);
        }

        $workspaceId = $user->current_workspace_id ?? $user->workspace_id;
        if ($workspaceId) {
            $workspace = Workspace::find($workspaceId);
            if ($workspace && $workspace->status === 'suspended') {
                abort(403, __('Your account has been temporarily suspended. Please contact support.'));
            }
        }

        return $next($request);
    }
}
