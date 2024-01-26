<?php

/**
 * @package WithdrawalService
 * @author tehcvillage <support@techvill.org>
 * @contributor Ashraful Rasel <[ashraful.techvill@gmail.com]>
 * @created 27-12-2022
 */

namespace App\Services;

use App\Exceptions\Api\V2\{
    WithdrawalException,
    PaymentFailedException
};
use App\Http\Helpers\Common;
use App\Http\Resources\V2\FeesResource;
use App\Models\{
    AutoPayout,
    Wallet,
    CurrencyPaymentMethod,
    FeesLimit,
    Withdrawal
};
use Exception, DB;
use App\Services\Mail\Withdrawal\WithdrawalViaAdminMailService;

class WithdrawalMoneyService
{
    /**
     * @var Common;
     */
    protected $helper;


    /**
     * Construct the common helper class
     *
     * @param Common $helper
     *
     * @return void
     */
    public function __construct(Common $helper)
    {
        $this->helper = $helper;
    }

    /**
     * Get list of Withdrawal currencies
     *
     * @param int $paymentMethod & $cryptoCurrencyId
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws WithdrawalException
     */
    public function getCurrencies($paymentMethod, $cryptoCurrencyId)
    {
        $currencies = [];
        $condition = (!empty($cryptoCurrencyId)) ? ['user_id' => auth()->id(), 'currency_id' => $cryptoCurrencyId] : ['user_id' => auth()->id()];

        $wallets = Wallet::with(['active_currency:id,code,type', 'active_currency.fees_limit:id,currency_id'])
                    ->whereHas('active_currency', function ($q) use ($paymentMethod) {
                        $q->whereHas('fees_limit', function ($query) use ($paymentMethod) {
                            $query->hasTransaction()->transactionType(Withdrawal)->where('payment_method_id', $paymentMethod);
                        });
                    })
                    ->where($condition)
                    ->get(['currency_id', 'is_default'])
                    ->map(function($items) use (&$currencies) {
                        $currencies[optional($items->active_currency)->id] =[
                            'id' => optional($items->active_currency)->id,
                            'code' => optional($items->active_currency)->code,
                            'type' => optional($items->active_currency)->type,
                            'default_wallet' => $items->is_default,
                        ];
                        return $currencies;
                    });

        if (0 == count($currencies)) {
            throw new WithdrawalException(__("No :x found.", ["x" => __("withdrawal currency")]));
        }
        return collect(array_values($currencies));
    }

    /**
     * Validate withdrawal amount with currency id
     *
     * @param int $currencyId
     * @param int $paymentMethod
     * @param int $withdrawalSettingId
     * @param double $amount
     * @param int $userId
     * @return array
     * @throws WithdrawalException
     */

    public function validateAmountLimit($currencyId, $paymentMethod_id, $amount, $userId)
    {
        //$withdrawalSetting = $this->getWithdrawalSettings($paymentMethod_id, $userId);
       
        $feesDetails = $this->helper->transactionFees($currencyId, $amount, Withdrawal, $paymentMethod_id);
       
        $this->helper->amountIsInLimit($feesDetails, $amount);
        
        $this->helper->checkWalletAmount($userId, $currencyId, $feesDetails->total_amount);
        
       // $feesArray = [
         //   'payout_setting_id' => $withdrawalSettingId,
          //  'payoutSetting' => $withdrawalSetting,
        //];

        return array_merge((new FeesResource($feesDetails))->toArray(request())) ;

    }
    
      public function getPaymentMethods($currencyId, $currencyType, $transactionType, $platform = 'mobile')
    {
        $condition = ($currencyType == 'fiat') ? getPaymoneySettings('payment_methods')[$platform]['fiat']['withdrawal'] : getPaymoneySettings('payment_methods')[$platform]['crypto']['withdrawal'];

        $feesLimits = FeesLimit::whereHas('currency', function ($q) {
            $q->where('status', '=', 'Active');
        })
            ->whereHas('payment_method', function ($q) use ($condition) {
                $q->whereIn('id', $condition)->where('status', '=', 'Active');
            })
            ->where(['transaction_type_id' => $transactionType, 'has_transaction' => 'Yes', 'currency_id' => $currencyId])
            ->get(['payment_method_id']);
        
        $currencyPaymentMethods = CurrencyPaymentMethod::where('currency_id', $currencyId)->where('activated_for', 'like', "%withdrawal%")->get(['method_id', 'type', 'alias']);
        $currencyPaymentMethodFeesLimitCurrenciesList = $this->currencyPaymentMethodFeesLimitCurrencies($feesLimits, $currencyPaymentMethods);
           
        return $currencyPaymentMethodFeesLimitCurrenciesList;
    }
    
    public function currencyPaymentMethodFeesLimitCurrencies($feesLimits, $currencyPaymentMethods)
    {
        
        $selectedCurrencies = [];
        foreach ($feesLimits as $feesLimit) {
            foreach ($currencyPaymentMethods as $currencyPaymentMethod) {

                if ($feesLimit->payment_method_id == $currencyPaymentMethod->method_id) {
                    $selectedCurrencies[$feesLimit->payment_method_id]['id']   = $feesLimit->payment_method_id;
                    $selectedCurrencies[$feesLimit->payment_method_id]['name'] = optional($feesLimit->payment_method)->name;
                    $selectedCurrencies[$feesLimit->payment_method_id]['alias'] = strtolower(preg_replace("/\s+/", "", optional($feesLimit->payment_method)->name));
                    $selectedCurrencies[$feesLimit->payment_method_id]['partner'] = $currencyPaymentMethod->alias;
                    $selectedCurrencies[$feesLimit->payment_method_id]['type'] = $currencyPaymentMethod->type;
                }
            }
        }
      
        return $selectedCurrencies;
    }

    /**
     * Store withdrawal money
     *
     * @param int $userId
     * @param int $currencyId
     * @param double $amount
     * @param double $totalAmount
     * @param int $withdrawalSettingId
     * @throws WithdrawalException
     * @throws PaymentFailedException
     */

    public function withdrawalConfirm($userId, $currencyId, $amount, $totalAmount, $paymentMethodId, $paymentDetails)
    {

        $uuid = unique_code();

        $wallet = $this->helper->getWallet($userId, $currencyId, ['id', 'currency_id', 'balance']);

        $feesDetails = $this->helper->transactionFees(
                            $currencyId,
                            $amount,
                            Withdrawal,
                            $paymentMethodId
                        );

        $this->helper->amountIsInLimit($feesDetails, $amount);

        $this->helper->checkWalletAmount($userId, $currencyId, $totalAmount);

        $arr = [
            'user_id'             => $userId,
            'wallet'              => $wallet,
            'currency_id'         => $wallet->currency_id,
            'payment_method_id'   => $paymentMethodId,
            'uuid'                => $uuid,
            'percentage'          => $feesDetails->charge_percentage,
            'charge_percentage'   => $feesDetails->fees_percentage,
            'charge_fixed'        => $feesDetails->charge_fixed,
            'amount'              => $amount,
            'totalAmount'         => $feesDetails->total_amount,
            'subtotal'            => $amount - $feesDetails->total_fees,
            'payment_method_info' => $paymentDetails,
        ];

        try {

            DB::beginTransaction();

            $withdrawal = new Withdrawal();
            $withdraw = $withdrawal->createWithdrawal($arr);

            $arr['transaction_reference_id'] = $withdraw->id;

            $withdrawal->createWithdrawalDetail($arr);

            $withdrawal->createWithdrawalTransaction($arr);

            $withdrawal->updateWallet($arr);
            
            $receiver =  $paymentDetails["account_no"];
            
            if ($paymentDetails["type"]== "WALLET"){
                if (substr($receiver, 0, 4) === "+252") {
                    $receiver = "00252" . substr($receiver, 4);
                } elseif (substr($receiver, 0, 1) === "6") {
                    $receiver = "00252" . $receiver;
                }elseif (substr($receiver, 0, 1) === "7") {
                    $receiver = "00252" . $receiver;
                }
            }else{
                $receiver = str_replace("+252", "", $paymentDetails["account_no"]);
            }
            
            $autoPayout = new AutoPayout();
            $autoPayout->session = "PAY-{$uuid}";
            $autoPayout->reference = "N/A";
            $autoPayout->trx_reference = $uuid;
            $autoPayout->sender = auth()->user()->formattedPhone;
            $autoPayout->receiver = $receiver;
            $autoPayout->cleared_amount = $amount - $feesDetails->total_fees;
            $autoPayout->amount = $amount;
            $autoPayout->rate = $feesDetails->charge_percentage;
            $autoPayout->fee = $feesDetails->total_fees;
            $autoPayout->platform = "PaySomX";
            $autoPayout->payment_method = $paymentDetails["type"];
            $autoPayout->partner = $paymentDetails["partner"];
            $autoPayout->status = 1;
            $autoPayout->save();

            DB::commit();

            (new WithdrawalViaAdminMailService)->send($withdraw, 'payout');

            return [
                'status' => true,
                'tr_ref_id' => $withdraw->id,
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw new PaymentFailedException($e->getMessage());
        }


    }

    /**
     * get withdrawal setting
     *
     * @param int $paymentMethod
     * @param int $withdrawalSettingId
     * @throws WithdrawalException
     */

    public function getWithdrawalSettings($paymentMethod, $withdrawalSettingId, $userId)
    {
        $option = ($paymentMethod == Bank)
                    ? ['account_name', 'account_number', 'type', 'swift_code', 'bank_name']
                    : (($paymentMethod == Paypal)
                        ? ['email', 'type']
                        : ['crypto_address', 'type']);

        $withdrawalSetting = $this->helper->getPayoutSettingObject(
                ['paymentMethod:id,name'],
                ['id' => $withdrawalSettingId, 'user_id' => $userId],
                $option
            );

        if (!$withdrawalSetting) {
            throw new WithdrawalException(__("No :x found.", ["x" => __("withdrawal setting")]));
        }

        return $withdrawalSetting;
    }


}
