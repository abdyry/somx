<?php

namespace App\Services;

use App\Models\SmsQueue;
use App\Models\PhoneOTP;
use App\Models\SmsConfig;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class PaymentGatewayVerificationService {
    protected $data;
    
    public function process($data)
    {
        return true;
        $this->data = $data;
        $verification = "{$data->platform}Verify";
        $this->$verification();
    }
    
    public function premierWalletVerify() {
        try{
            $this->token = $this->verifyPremierloginMerchant();
            $phoneNumber = str_replace("+", "00", $this->data->phone);
            
            $url = "https://api.premierwallets.com/api/GetPaymentDetails";
            $headers = [
                'Content-Type: application/json',
                'MachineID: ds@#13ds!WE4C#FW$672@',
                'ChannelID: 104',
                'DeviceType: 205',
                'Authorization: Bearer ' .$this->token['Token']
            ];
           
            $data = [
                "TransactionID" => $this->data->Data["TransactionID"],
                "LoginUserName" => "911808"
            ];
            
            $postfields = json_encode($data);
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS,  $postfields);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            $result = curl_exec($curl);
            $response = json_decode($result, true);
            if ($response['Response']['Code']==001){
                if ($response['Data']['Status']=="Executed"){
                    $status = true;
                   }else{
                     $status = false;  
                   }
                if ($response['Data']['Status']=="Rejected"){
                    $IsRejected = true;
                   }else{
                     $IsRejected = false;  
                }
               return [
                    'status' => $status,
                    'IsRejected' => $IsRejected,
                    'message' => $response['Data']['Status'],
                    'CustomerName' => $response['Data']['CustomerName'],
                    'TransactionId' => $response['Data']['TransactionId'],
                ];
                
            }else{
                return [
                'status' => false,
                'message' => $response['Response']['Errors'][0]['Message'],
            ];
            }
            
        } catch (Exception $ex) {
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }
    
    public function verifyPremierloginMerchant() {
        try {
            $url = "https://api.premierwallets.com/api/MerchantLogin";
            $headers = [
                'Content-Type: application/json',
                'MachineID: ds@#13ds!WE4C#FW$672@',
                'ChannelID: 104',
                'DeviceType: 205',
                'Authorization: Basic SUNLWDlROlYxUkJIUQ=='
            ];
            
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            $result = curl_exec($curl);
            $resObj = json_decode($result);
            curl_close($curl);
            
            $data = json_decode($result, true);
               
            return [
                'status' => true,
                'Token' => $data['Data']['Token'],
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
    
    public function somtelVerify(){
        try{
            ob_start();
            $out = fopen('php://output', 'w');
            $request_param = ["apiKey" => "ImZETZJY1CdAGmiwoE0KszE1GgfzwpAYFS5ew8V0H", "invoiceId" => $this->data->InvoiceId];
            $json = json_encode($request_param, JSON_UNESCAPED_SLASHES);
            $hashed = hash('SHA256', $json."sjBxisw1DViqeZGQdeUBfrhzSkxP7XfBTuVtM5");
            $url = "https://edahab.net/api/api/CheckInvoiceStatus?hash=".$hashed;
            $headers = [
                'Content-Type: application/json'
            ];
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_POST, false);
            curl_setopt($curl, CURLOPT_POSTFIELDS,  $json);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_VERBOSE, true); 
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_STDERR, $out);
            $result = curl_exec($curl);
            $output = [];
            $output['result'] = $result;
            $output['info'] = curl_getinfo($curl);

            fclose($out);
            $output['debug'] = ob_get_clean();
            $response = json_decode($result, true);
            // print_r($output);
            // die();
            $status = $response['InvoiceStatus'] == "Paid";
            
            return [
                'status'    => $status,
                'message'   => $response['StatusDescription'] . ' Invoice ' .$response['InvoiceStatus'],
            ];
        } catch (Exception $ex) {
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }
}