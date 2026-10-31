<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SummaryController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('webhooks/stripe', StripeWebhookController::class);

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('summary', SummaryController::class);

    Route::get('customers', [CustomerController::class, 'index']);
    Route::post('customers', [CustomerController::class, 'store']);

    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::post('invoices', [InvoiceController::class, 'store']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->whereNumber('invoice');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->whereNumber('invoice');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->whereNumber('invoice');
    Route::post('invoices/{invoice}/payment', [PaymentController::class, 'store'])->whereNumber('invoice');
    Route::delete('invoices/{invoice}/payment', [PaymentController::class, 'destroy'])->whereNumber('invoice');
});
