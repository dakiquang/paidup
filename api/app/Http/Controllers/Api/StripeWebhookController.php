<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidWebhook;
use App\Http\Controllers\Controller;
use App\Payments\PaymentGateway;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, PaymentService $payments): Response
    {
        try {
            $event = $gateway->parseWebhook($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (InvalidWebhook) {
            return response()->noContent(400);
        }

        $payments->handle($event);

        return response()->noContent();
    }
}
