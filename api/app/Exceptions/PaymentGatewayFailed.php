<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class PaymentGatewayFailed extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'The payment provider is unavailable. Try again shortly.'], 502);
    }
}
