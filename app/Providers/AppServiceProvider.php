<?php

namespace App\Providers;

use App\Payments\FakePaymentGateway;
use App\Payments\HttpPaymentGateway;
use App\Payments\PaymentGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, fn () => match (config('services.payments.driver')) {
            'fake' => new FakePaymentGateway,
            default => new HttpPaymentGateway(
                config('services.payments.base_url'),
                config('services.payments.secret'),
            ),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
