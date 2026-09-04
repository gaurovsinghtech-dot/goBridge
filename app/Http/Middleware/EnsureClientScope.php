<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For client app routes: set current_client_id on the request from the authenticated user.
 * Controllers should use this to scope queries (e.g. only show data for the user's client).
 */
class EnsureClientScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->client_id) {
            $request->attributes->set('current_client_id', $user->client_id);
            if ($user->client && in_array($user->client->status, ['inactive', 'suspended'], true)) {
                abort(403, 'Account is suspended.');
            }
        }

        $workspaceId = $request->session()->get('current_workspace_id') ?: ($user?->current_workspace_id ?? $user?->workspace_id);
        if (! $workspaceId && $user) {
            $firstAccessible = $user->accessibleWorkspaces()->first();
            if ($firstAccessible) {
                $workspaceId = $firstAccessible->id;
                $user->forceFill(['workspace_id' => $workspaceId])->saveQuietly();
                $request->session()->put('current_workspace_id', $workspaceId);
            }
        }

        if ($workspaceId) {
            $request->attributes->set('current_workspace_id', (int) $workspaceId);
        }

        $workspace = $workspaceId ? \App\Models\Workspace::find($workspaceId) : ($user?->client?->workspaces()->first());
        if ($workspace && in_array($workspace->status ?? 'active', ['inactive', 'suspended'], true)) {
            abort(403, 'Workspace is suspended.');
        }

        return $next($request);
    }
}
