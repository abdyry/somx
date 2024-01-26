<?php

namespace App\Services;

use App\Models\PhoneOTP;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class OTPService {
    
    public function generate($recipient) {
        $otp = rand(100000, 999999);
        $phoneOTP = PhoneOTP::updateOrCreate(['phone' => $recipient], ['phone' => $recipient, 'otp'=> $otp, 'expires_at' => Carbon::now()->addMinutes(3)]);
        return $otp;
    }
    
    public function verify($recipient, $otp) {
        $status = PhoneOTP::where("phone", $recipient)->where("otp", $otp)->where("expires_at", ">", Carbon::now())->exists();
        return $status;
    }
}