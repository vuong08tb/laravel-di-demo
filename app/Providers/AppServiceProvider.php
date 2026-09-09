<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
// use App\Services\PaymentGateway\PaypalPaymentGateway;
use App\Contracts\StorageDriverInterface;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\VideoController;
use App\Services\LocalStorageDriver;
use App\Services\PaymentGateway\StripePaymentGateway;
use App\Services\S3StorageDriver;
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

        // // TODO: Thực hiện dòng lệnh bind Interface với PaypalPaymentGateway cụ thể tại đây
        // $this->app->bind(
        //     PaymentGatewayInterface::class,
        //     PaypalPaymentGateway::class
        // );
        // Contextual binding: cùng 1 interface, mỗi controller nhận 1 implementation khác nhau.
        $this->app->when(AvatarController::class)
            ->needs(StorageDriverInterface::class)
            ->give(LocalStorageDriver::class);
        $this->app->when(VideoController::class)
            ->needs(StorageDriverInterface::class)
            ->give(S3StorageDriver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
