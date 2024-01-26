<?php

namespace App\Services;

use App\Models\SmsQueue;
use App\Models\SmsConfig;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SmsService {
    private $channel;
    
    public function sendSMS($recipient, $type, $body = '') {
        $this->recipient = $recipient;
        $this->type = $type;
        $this->body = $body;
        $this->channel = $this->getChannel();
        if ($this->channel->config->requiresBearerToken) {
            $token = $this->getToken();
            $response = Http::withToken($token)->withBody(json_encode($this->getBody()))->post($this->channel->config->sendEndpoint);
            $body = json_decode($response->body(), true);
            $status = ($response->successful() ? ($body["Result"]["Status"] ?? $body["ResponseCode"]) : false) == 200;
            return $status;
        }
    }
    
    public function getBody() {
        $body = $this->body;
        if ($this->type == "OTP") {
            $body = $this->getBodyOTP();
        }
        return ["mobile" => $this->recipient, "message" => $body];
    }
    
    public function getToken() {
        $response;
        if (strtolower($this->channel->config->tokenContentType) == "application/x-www-form-urlencoded") {
            $response = Http::asForm()->post($this->channel->config->tokenEndpoint, (array) $this->channel->config->tokenBody);
        } else if (strtolower($this->channel->config->tokenContentType) == "application/json") {
            $response = Http::withBody(json_encode($this->channel->config->tokenBody), $this->channel->config->tokenContentType)->post($this->channel->config->tokenEndpoint);
        }
        return $response->successful() ? $response["access_token"] : rand(23432,2334233);
    }
    
    public function getChannel() {
        $recipient = $this->recipient;
        if (str_starts_with($recipient, "+252") && strlen($recipient) == 13) {
            $this->_recipient = str_replace("+252", "", $recipient);
            if (preg_match('/^(61|68|77|63|9)\d{7}$/', $this->_recipient, $matches)) {
                return SmsConfig::where("provider", "hormuud")->where("status", "Active")->firstOrFail();
            }
            
            if (preg_match('/^62\d{7}$/', $this->_recipient, $matches)) {
                return SmsConfig::where("provider", "somtel")->where("status", "Active")->firstOrFail();
            }
        }
        return SmsConfig::where("status", "Active")->firstOrFail();
    }
    
    public function getBodyOTP() {
        $otp = (new OTPService())->generate($this->recipient);
        return "Your OTP code is $otp";
    }
}