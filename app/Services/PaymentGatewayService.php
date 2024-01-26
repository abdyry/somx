<?php
namespace App\Services;
use App\Models\PaymentMethod;
use App\Jobs\verifyDepositPayment;

class PaymentGatewayService
{
    protected $token;
    protected $request;
    
    public function mobilePaymentWithdraw($request) {
        try{
            $this->request = $request;
            
            $payment_method = (new PaymentMethod())->where("id", $request->payment_method_id)->firstOrFail()->name;
            $_payment_method = lcfirst($payment_method) . "Withdraw";
            
            if($payment_method && method_exists($this, $_payment_method)) {
                return $this->$_payment_method();
            }
            
            return [
                'status' => false,
                'message' => "You have selected invalid payment method",
            ];
        } catch (Exception $ex) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
            
    //Hormuud Api
    public function hormuudWithdraw() {
        try{
            $request = $this->request;
            $timestamp = date("Y-m-d H:i:s");
            $uuid = unique_code();
            $phoneNumber = str_replace("+", "", $request->phone);
            
            $BODY = json_encode([
                "schemaVersion" => "1.0",
    			"requestId" => $uuid,
    			"timestamp" => $timestamp,
    			"channelName" => "WEB",
    			"serviceName" => "API_PURCHASE",
    			"serviceParams" => [
        			"merchantUid" => "M0912362",
        			"apiUserId" => "1005203",
        			"apiKey" => "API-1081628967AHX",
        			"paymentMethod" => "mwallet_account",
        			"payerInfo" => ["accountNo" => $phoneNumber],
        			"transactionInfo"=>[
            			"referenceId" => $uuid,
            			"invoiceId" => $uuid,
            			"amount" => $request->amount,
            			"currency" => "USD",
            			"description" => "DESCRIPTION? NAHHH"
        			],
    			],
    		]);
    		
    		$URL = "https://api.waafipay.net/asm";
            $CURLOPTS =  [
                CURLOPT_URL => $URL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_POST => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER  => ["Content-type: application/json"],
                CURLOPT_POSTFIELDS => $BODY
            ];
            
    		$CURL = curl_init();
    		curl_setopt_array($CURL, $CURLOPTS);
    		$result = curl_exec($CURL);
    		$response = json_decode($result);
    		$response->isSuccess = false;
    	
    		if(isset($response->responseCode)) {
    		    $response->isSuccess = ($response->responseCode == "2001" && $response->params->state =="APPROVED");
    		    //["2001" => "SUCCESS", 5310" => "USER_REJECTED", "5206" => "INCORRECT_PIN|INSUFFICIENT_BALANCE", "5311" => "TIMED_OUT"];
    		}
    		
    		 if ($response->isSuccess){
                return [
                    'status' => true,
                    'message' => $response->params->state,
                    'SecretKey' => $response->params->transactionId,
                    'TransactionId' => $response->responseId,
                ];
    		 }
            
            return [
                    'status' => false,
                    'message' => $response->params->description,
            ];
        } catch (Exception $e) {
            return [
                'success' => $e->getMessage()
            ];
        }
    }
    
    //Premierwallet Agent API
    //public function premierWalletWithdraw() {
    //     try{
    //         $request = $this->request;
    //         $token = $this->verifyPremierlogin('login');
    //         $endpoint = "InitiateCashOut";
    //         $phoneNumber = str_replace("+", "00", $request->phone);
            
    //         $url = "https://agent.premierwallets.com:448/api/" . $endpoint;
    //         $headers = [
    //             'Content-Type: application/json',
    //             'MachineID: ds@#13ds!WE4C#FW$672@',
    //             'ChannelID: 104',
    //             'DeviceType: 205',
    //             'Authorization: Bearer ' .$token['Token']
    //         ];
            
    //         $data = [
    //             "Amount" => $request->amount,
    //             "Fee" => 0.00,
    //             "WalletId" => $phoneNumber,
    //             "Note" => "withdraw with SomXchange",
    //             "TransactionType" => 4
    //         ];
    //         $postfields = json_encode($data);
    //         $curl = curl_init($url);
    //         curl_setopt($curl, CURLOPT_POST, true);
    //         curl_setopt($curl, CURLOPT_INTERFACE, "213.139.204.162");
    //         curl_setopt($curl, CURLOPT_POSTFIELDS,  $postfields);
    //         curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    //         curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    //         $result = curl_exec($curl);
    //         $response = json_decode($result, true);
          
    //         if ($response['Response']['Code']==001){
    //           return [
    //                 'status' => true,
    //                 'message' => $response['Response']['Messages'],
    //                 'SecretKey' => $response['Data']['SecretKey'],
    //                 'TransactionId' => $response['Data']['TransactionId'],
    //             ];
                
    //         }else{
    //             return [
    //             'status' => false,
    //             'message' => $response['Response']['Errors'][0]['Message'],
    //         ];
    //         }
            
    //     } catch (Exception $ex) {
    //         return [
    //             'status' => false,
    //             'message' => $e->getMessage(),
    //         ];
    //         }
    // }
    
    // //Premierwallet Agent Login API
    // public function verifyPremierlogin($endpoint) {
    //     try {
    //         $url = "https://agent.premierwallets.com:448/api/" . $endpoint;
    //         $headers = [
    //             'Content-Type: application/json',
    //             'MachineID: ds@#13ds!WE4C#FW$672@',
    //             'ChannelID: 104',
    //             'DeviceType: 205',
    //             'Authorization: Basic QTAwMTU2Ok9sb3dAMDA3MQ=='
    //         ];
            
    //         $curl = curl_init($url);
    //         curl_setopt($curl, CURLOPT_POST, true);
    //         curl_setopt($curl, CURLOPT_INTERFACE, "213.139.204.162");
    //         curl_setopt($curl, CURLOPT_POSTFIELDS, $endpoint == "login" ? false : json_encode($request));
    //         curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    //         curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    //         $result = curl_exec($curl);
    //         $resObj = json_decode($result);
    //         curl_close($curl);
            
    //         $data = json_decode($result, true);
                   
    //         return [
    //             'status' => true,
    //             'Token' => $data['Data']['Token'],
    //         ];
    //     } catch (Exception $e) {
    //         return [
    //             'status' => false,
    //             'message' => $e->getMessage(),
    //         ];
    //     }
    // }
    
    //Premierwallet Merchant API
    public function premierWalletWithdraw() {
        try{
            $request = $this->request;
            $this->token = $this->verifyPremierloginMerchant('MerchantLogin');
            $endpoint = "PushPayment";
            $phoneNumber = str_replace("+", "00", $request->phone);
            
            $url = "https://api.premierwallets.com/api/" . $endpoint;
            $headers = [
                'Content-Type: application/json',
                'MachineID: ds@#13ds!WE4C#FW$672@',
                'ChannelID: 104',
                'DeviceType: 205',
                'Authorization: Bearer ' .$this->token['Token']
            ];
           
            $data = [
                "Amount" => $request->amount,
                "Category" => 1,
                "CustomerWalletID" => $phoneNumber,
                "Remarks" => "withdraw with SomXchange",
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
                $response["phone"] = $phoneNumber;
                $response["platform"] = "PREMIERWALLET";
                dispatch(new verifyDepositPayment((object) $response));
                return [
                    'status' => true,
                    'message' => $response['Response']['Messages'],
                    'TransactionId' => $response['Data']['TransactionID'],
                ];
                
            }
            return [
                'status' => false,
                'message' => $response['Response']['Errors'][0]['Message'],
            ];
        } catch (Exception $ex) {
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }
    
    public function verifyPremierloginMerchant($endpoint) {
        try {
            $url = "https://api.premierwallets.com/api/" . $endpoint;
            $headers = [
                'Content-Type: application/json',
                'MachineID: ds@#13ds!WE4C#FW$672@',
                'ChannelID: 104',
                'DeviceType: 205',
                'Authorization: Basic SUNLWDlROlYxUkJIUQ=='
            ];
            
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $endpoint == "MerchantLogin" ? false : json_encode($request));
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
    
    //Somtel Merchant payemnt API
    public function somtelWithdraw() {
        try{
            $request = $this->request;
            $secret_key = "sjBxisw1DViqeZGQdeUBfrhzSkxP7XfBTuVtM5";
            
            $apikey = "ImZETZJY1CdAGmiwoE0KszE1GgfzwpAYFS5ew8V0H";
            $agentCode = "087493";
            $returnUrl = "https://pay.somxchange.com/deposit/success";
            $phoneNumber = str_replace("+252", "", $request->phone);
            
            $headers = [
                'Content-Type: application/json'
            ];
           
            $data = [
                "edahabNumber" => $phoneNumber,
                "amount" => $request->amount,
                "apiKey" => $apikey,
                "agentCode" => "087493",
                "returnUrl" => $returnUrl
            ];
            $postfields = json_encode($data);
            $hashed = hash('SHA256', $postfields.$secret_key);
            $url = "https://edahab.net/api/api/IssueInvoice?hash=" . $hashed;
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS,  $postfields);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            $result = curl_exec($curl);
            $response = json_decode($result, true);
            
            if ($response['StatusCode']==0){
                $InvoiceId = $response['InvoiceId'];
                $response["platform"] = "SOMTEL";
                dispatch(new verifyDepositPayment((object) $response));
                return [
                    'status' => true,
                    'url' => "https://edahab.net/API/Payment?invoiceId=$InvoiceId",
                    'TransactionId' => $response['InvoiceId'],
                ];
            }
            return [
                'status'        => false,
                'message'       => $response['ValidationErrors'][0]['ErrorMessage'],
            ];
        } catch (Exception $ex) {
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }
}
