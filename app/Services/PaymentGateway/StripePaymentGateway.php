<?php

namespace App\Services\PaymentGateway;

use App\Contracts\PaymentGatewayInterface;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function charge(float $amount): string
    {
        return "Charging {$amount} using Stripe.";
    }
}
