<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\PaymentGateway\PaypalPaymentGateway;
use App\Services\PaymentGateway\StripePaymentGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // TODO: Thực hiện dòng lệnh bind Interface với StripePaymentGateway cụ thể tại đây
        $this->app->bind(
            PaymentGatewayInterface::class,
            StripePaymentGateway::class
        );

        // TODO: Thực hiện dòng lệnh bind Interface với PaypalPaymentGateway cụ thể tại đây
        $this->app->bind(
            PaymentGatewayInterface::class,
            PaypalPaymentGateway::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
