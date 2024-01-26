<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\PaymentGatewayService;

class PaymentGatewaysController extends Controller
{
    public function waafiWithdraw(Request $request) {
        return (new PaymentGatewayService)->hormuudWithdraw($request);
    }
}
