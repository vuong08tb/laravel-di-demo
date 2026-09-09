<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    protected PaymentGatewayInterface $paymentGateway;

    // TODO: Inject interface chứ KHÔNG inject Concrete Class
    public function __construct(PaymentGatewayInterface $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

    public function checkout(): Response
    {
        // Thực hiện thanh toán thử $150.00
        $message = $this->paymentGateway->charge(150.00);

        return response($message);
    }
}
