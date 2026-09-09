<?php

namespace App\Services\PaymentGateway;

use App\Contracts\PaymentGatewayInterface;

class PaypalPaymentGateway implements PaymentGatewayInterface
{
    public function charge(float $amount): string
    {
        return "Charging {$amount} using PayPal.";
    }
}
