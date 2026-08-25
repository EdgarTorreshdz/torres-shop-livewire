<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

class CheckoutSuccessController extends Controller
{
    public function __invoke(Order $order): View
    {
        return view('storefront.checkout-success', [
            'order' => $order->load('items'),
        ]);
    }
}
