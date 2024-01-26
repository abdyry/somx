<?php

namespace App\Http\Controllers\Api\V2;
use Exception;
use App\Exceptions\Api\V2\{
    AmountLimitException,
    WithdrawalException,
    PaymentFailedException,
    WalletException
};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Helpers\Common;
use App\Models\{FeesLimit,
    WithdrawalDetail,
    PayoutSetting,
    Transaction,
    Withdrawal,
    User,
    Wallet
};
use App\Http\Requests\Api\V2\Payout\{
    AmountLimitRequest,
    ConfirmRequest
};
use App\Services\PayoutMoneyService;

class PayoutMoneyController extends Controller
{
    public $successStatus      = 200;
    public $unauthorisedStatus = 401;
    protected $helper;
    protected $withdrawal;
    protected $service;

    public function __construct(Common $helper, PayoutMoneyService $service)
    {
        $this->helper = $helper;
        $this->service = $service;
    }

    //Check User Agent
    public function checkAgent()
    {
        $AgentDetail = user::where(['phone' => request('agentnumber')])
                        ->where('Type', 'agent')
                        ->where('status', 'Active')
                        ->get(['first_name', 'last_name', 'phone']);
        return response()->json([
            'status'         => $this->successStatus,
            'agent_info' => $AgentDetail,
        ]);
    }
    
        /**
     * Validate requested amount & currency against User's wallet and System settings
     *
     * @param AmountLimitCheckRequest $request
     *
     * @return JsonResponse
     */
    public function amountLimitCheck(AmountLimitRequest $request)
    {
        try {
            extract($request->only(['currency_id', 'payment_method_id', 'amount']));
            //var_dump();
           // die();
            return $this->successResponse(
                $this->service->validateAmountLimit(
                    $currency_id,
                    $payment_method_id,
                    $amount,
                    auth()->id()
                )
            );
            
        } catch (WithdrawalException $e) {
            return $this->unprocessableResponse([], $e->getMessage());
        } catch (AmountLimitException | WalletException $e) {
            return $this->unprocessableResponse([], $e->getMessage());
        } catch (\Exception $e) {
            return $this->unprocessableResponse([], __("Failed to process the request."));
        }

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

       $feesDetails = $this->helper->transactionFees($currencyId, $amount, Withdrawal, $paymentMethod_id);
       
        $this->helper->amountIsInLimit($feesDetails, $amount);
        
        $this->helper->checkWalletAmount($userId, $currencyId, $feesDetails->total_amount);
        
        return array_merge((new FeesResource($feesDetails))->toArray(request())) ;

    }
    
    /**
     * Confirm and complete Withdrawal process
     *
     * @param confirm $request
     *
     * @return JsonResponse
     */
    public function confirm(ConfirmRequest $request)
    {
        try {
            extract($request->only(['currency_id', 'amount', 'payment_method_id', 'agent_no']));
            $totalAmount = $amount;
            $response = $this->service->payoutConfirm(
                auth()->id(),
                $currency_id,
                $amount,
                $totalAmount,
                $payment_method_id,
                $agent_no
            );
        } catch (WithdrawalException $exception) {
            return $this->unprocessableResponse([], $exception->getMessage());
        } catch (PaymentFailedException $exception) {
            return $this->unprocessableResponse($exception->getMessage());
        } catch (\Exception $exception) {
            return $this->unprocessableResponse($exception->getMessage());
        }
        return $this->okResponse($response);
    }

}
