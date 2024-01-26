<?php

/**
 * @package StripeProcessor
 * @author tehcvillage <support@techvill.org>
 * @contributor Md Abdur Rahaman Zihad <[zihad.techvill@gmail.com]>
 * @created 21-12-2022
 */


namespace App\Services\Gateways\PremierWallet;

use App\Services\Gateways\Gateway\Exceptions\{
    GatewayInitializeFailedException
};
use App\Services\Gateways\Gateway\PaymentProcessor;
use Exception;
use Stripe\StripeClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @method array pay()
 */
class PremierWalletProcessor extends PaymentProcessor
{
    protected $data;
    
    protected $token;

    protected $premierwallet;

    /**
     * Initiate the stripe payment process
     *
     * @param array $data
     *
     * @return void
     */
    protected function pay(array $data) : array
    {
        $data['payment_method_id'] = Stripe;

        // Boot stripe payment initiator
        $this->boot($data);

        // create payment intent
        $response =  $this->createPaymentIntent(
            $data['totalAmount'],
            $this->currency,
        );

        if ($response['status'] == false) {
            throw new GatewayInitializeFailedException(__("Stripe initialize failed."));
        }

        return $response;
    }

    /**
     * Boot stripe payment processor
     *
     * @param array $data
     *
     * @return void
     */
    protected function boot($data)
    {
        $this->data = $data;

        $this->paymentCurrency();

        $this->premierwellet = $this->paymentMethodCredentials();

        if (!$this->stripe->secret_key) {
            throw new GatewayInitializeFailedException(__("Stripe initialize failed."));
        }
    }


    public function createPaymentIntent($amount, $currency, $phone, $endpoint) {
        try {
        
        $token = $this->verifylogin('login');
        
        $url = "https://agent.premierwallets.com:448/api/" . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'MachineID: ds@#13ds!WE4C#FW$672@',
            'ChannelID: 104',
            'DeviceType: 205',
            'Authorization: Bearer ' .$token['Token']
        ];
        
        $data = array(
            "Amount" => $amount,
            "Fee" => 0.00,
            "WalletId" => $phone,
            "Note" => "withdraw with SomXchange",
            "TransactionType" => 4
        );

        $postfields = json_encode($data);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_INTERFACE, "213.139.204.162");
        curl_setopt($curl, CURLOPT_POSTFIELDS,  $postfields);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($curl);
        $data = json_decode($result, true);
        curl_close($curl);

        
          return [
                'status' => true,
                'message' => $data['Response']['Messages'],
                'SecretKey' => $data['Data']['SecretKey'],
                'TransactionId' => $data['Data']['TransactionId'],
            ];
        } catch (Exception) {
            return [
                'status' => false,
                'message' => $data['Response']['Errors'][0]['Message'],
            ];
            die();
        }
    }

    /**
     * Get gateway alias name
     *
     * @return string
     */
    public function gateway(): string
    {
        return "stripe";
    }

    public function verify($request, $endpoint)
    {
        try {
        
            
       $token = $this->verifylogin('login');
            
        $url = "https://agent.premierwallets.com:448/api/" . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'MachineID: ds@#13ds!WE4C#FW$672@',
            'ChannelID: 104',
            'DeviceType: 205',
            'Authorization: Bearer ' .$token['Token']
        ];
        
        $data = [
            "ReferenceId" => $request->ReferenceId,
            ];

        $postfields = json_encode($data);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_INTERFACE, "213.139.204.162");
        curl_setopt($curl, CURLOPT_POSTFIELDS,  $postfields);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($curl);
        $data = json_decode($result, true);
        curl_close($curl);
        print_r($data);
          return [
                'status' => true,
                'message' => $data['Response']['Messages'],
                'Token' => $data['Data']['Token'],
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
 /**
            $data = getPaymentParam($request->params);
            $data['payment_method_id'] = Stripe;
            $this->setPaymentType($data['payment_type']);
            $this->boot($data);

            $stripe = new StripeClient($this->stripe->secret_key);
            $paymentIntent = $stripe->paymentIntents->retrieve(
                $request->payment_intent,
                []
            );

            if ($paymentIntent['status'] == 'succeeded') {

                $payment = callAPI(
                    'GET',
                    $data['redirectUrl'],
                    [
                        'params' => $request->params,
                        'execute' => 'api'
                    ]
                );

                $data ['transaction_id'] = $payment;

                return $data;
            }

            throw new GatewayInitializeFailedException(__("Stripe Payment failed."));


        } catch (Exception $e) {

            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
     */

    }

 public function verifylogin($endpoint) {
        try {
               
        $url = "https://agent.premierwallets.com:448/api/" . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'MachineID: ds@#13ds!WE4C#FW$672@',
            'ChannelID: 104',
            'DeviceType: 205',
            'Authorization: Basic QTAwMTU2Ok9sb3dAMDA3MQ=='
        ];
        
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_INTERFACE, "213.139.204.162");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $endpoint == "login" ? false : json_encode($request));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($curl);
        $resObj = json_decode($result);
        curl_close($curl);
        
        $data = json_decode($result, true);
               
          return [
                'status' => true,
                'message' => $data['Response']['Messages'],
                'Token' => $data['Data']['Token'],
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }



    /**
     * Method paymentView
     *
     * @return void
     */
    public function paymentView()
    {
        return 'gateways.'.$this->gateway();
    }


    public function resolveFactor($currency)
    {
        $zeroDecimalCurrencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies)) {
            return 1;
        }

        return 100;
    }

}
