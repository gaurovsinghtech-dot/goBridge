<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CrmConnection;
use App\Services\Crm\Connectors\CrmManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CrmWebhookController extends Controller
{
    public function __construct(
        protected CrmManager $crmManager
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        $driver = $this->crmManager->driver($provider);
        if (! $driver) {
            return response()->json(['error' => 'Unknown CRM provider'], 404);
        }

        $payload = $request->all();
        $headers = $request->headers->all();

        try {
            $result = $driver->handleWebhook($payload, $headers);

            // Log webhook ingress
            $workspaceId = $request->input('workspace_id')
                ?? CrmConnection::where('provider', $provider)->where('status', 'active')->value('workspace_id')
                ?? 1;

            $this->crmManager->logSync(
                (int) $workspaceId,
                $provider,
                'webhook',
                'receive',
                'inbound',
                'success',
                null,
                null,
                null,
                $payload
            );

            return response()->json([
                'success' => true,
                'message' => 'CRM webhook processed successfully',
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error("[CrmWebhook] Error processing {$provider} webhook: ".$e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
