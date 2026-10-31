<?php

namespace App\Providers;

use App\Payments\PaymentGateway;
use App\Payments\StripeGateway;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => new StripeGateway(
            new StripeClient(['api_key' => config('services.stripe.secret') ?: null]),
            (string) config('services.stripe.webhook_secret'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
