<?php

namespace App\Http\Requests\Api\V2\Payout;

use App\Rules\CheckWithdrwalMethod;
use App\Http\Requests\CustomFormRequest;

class AmountLimitRequest extends CustomFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "currency_id" => 'required|integer|exists:currencies,id',
            "payment_method_id" =>'required|exists:payment_methods,id',
            "amount" => 'required|numeric'
        ];
    }
}
