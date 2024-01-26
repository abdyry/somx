<?php
/**
 * @package WithdrawalController
 * @author tehcvillage <support@techvill.org>
 * @contributor Ashraful <[ashraful.techvill@gmail.com]>
 * @created 27-12-2022
 */

namespace App\Http\Controllers\Api\V2;

use Illuminate\Http\JsonResponse;
use Exception;
use App\Exceptions\Api\V2\{
    AmountLimitException,
    WithdrawalException,
    PaymentFailedException,
    WalletException
};
use App\Http\Requests\Api\V2\Withdrawal\{
    CurrencyRequest,
    GetPaymentMethodRequest,
    AmountLimitRequest,
    ConfirmRequest
};
use App\Services\WithdrawalMoneyService;
use App\Http\Controllers\Controller;

/**
 * @group  Withdrawal Money
 *
 * API to manage Withdrawal money
 */
class WithdrawalController extends Controller
{

    /**
     * @var WithdrawalMoneyService
     */
    protected $service;

    public function __construct(WithdrawalMoneyService $service)
    {
        $this->service = $service;

    }

    /**
     * Get Withdrawal currency list by Payment Method
     * Get crypto withdrwal currency list if currency id is provided
     * @return JsonResponse
     * @throws WithdrawalException
     */
    public function getCurrencies(CurrencyRequest $request)
    {
        try {
            return $this->okResponse(
                $this->service->getCurrencies($request->payment_method, $request->currency_id)
            );
        } catch (WithdrawalException $e) {
            return $this->unprocessableResponse([], $e->getMessage());
        } catch (Exception $e) {
            return $this->unprocessableResponse([], __("Failed to process the request."));
        }
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
     * Get available payment method for the currency
     *
     * @return JsonResponse
     */
    public function getPaymentMethod(GetPaymentMethodRequest $reqeust): JsonResponse
    {
        try {
            return $this->successResponse($this->service->getPaymentMethods(
                $reqeust->currency_id,
                $reqeust->currency_type,
                $reqeust->transaction_type
            ));
        } catch (Exception $exception) {
            return $this->unprocessableResponse([], __("Failed to process the request."));
        }
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
            extract($request->only(['currency_id', 'amount', 'payment_method_id', 'details']));
            $totalAmount = $amount;
            $response = $this->service->withdrawalConfirm(
                auth()->id(),
                $currency_id,
                $amount,
                $totalAmount,
                $payment_method_id,
                $details
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
