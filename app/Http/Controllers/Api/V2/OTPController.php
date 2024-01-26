<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Jobs\SendSMS;
use Illuminate\Http\Request;
use App\Services\{SmsService, OTPService};

class OTPController extends Controller
{
    public function send(Request $request) {
        // SendSMS::dispatch($request->recipient, 'OTP');
        $status = (new SmsService())->sendSMS($request->recipient, 'OTP');
        return response()->json(["success" => $status], 200);
    }
    
    public function verify(Request $request) {
        $status = (new OTPService())->verify($request->recipient, $request->otp);
        return response()->json(["success" => $status], 200);
    }
}
