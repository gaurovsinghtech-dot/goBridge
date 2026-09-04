<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\Gateways\RazorpayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayWebhookController extends Controller
{
    public function __construct(private readonly RazorpayGateway $gateway) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $headers = $request->headers->all();

        $result = $this->gateway->handleWebhook($payload, $headers);

        return response()->json($result, $result['status'] ?? 200);
    }
}
