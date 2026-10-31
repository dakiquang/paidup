<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function store(Request $request, int $invoice): JsonResponse
    {
        $payment = $this->payments->start($request->user()->invoices()->findOrFail($invoice));

        return response()->json([
            'id' => $payment->id,
            'status' => $payment->status,
            'client_secret' => $payment->client_secret,
            'last_error' => $payment->last_error,
        ]);
    }

    public function destroy(Request $request, int $invoice): Response
    {
        $this->payments->cancel($request->user()->invoices()->findOrFail($invoice));

        return response()->noContent();
    }
}
