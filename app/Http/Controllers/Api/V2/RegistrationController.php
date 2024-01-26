<?php

/**
 * @package RegistrationController
 * @author tehcvillage <support@techvill.org>
 * @contributor Md Abdur Rahaman <[abdur.techvill@gmail.com]>
 * @created 06-12-2022
 */

namespace App\Http\Controllers\Api\V2;

use App\Exceptions\Api\V2\RegistrationException;
use App\Services\RegistrationService;
use Illuminate\Http\Request;
use App\Http\Requests\{
    CheckDuplicatePhoneNumberRequest,
    CheckDuplicateEmailRequest,
    UserStoreRequest
};
use App\Models\{
    User
};

use DB, Exception;
use App\Http\Controllers\Controller;

class RegistrationController extends Controller
{
    protected $service;

    public function __construct(RegistrationService $service)
    {
        $this->service = $service;
    }

    /**
     * Check duplicate email during registration
     *
     * @param CheckDuplicateEmailRequest $request
     * @return JsonResponse
     */
    public function checkDuplicateEmail(Request $request)
    {
        $emailExists = User::where("email", $request->email)->exists();
        return $this->successResponse([
            'status' => $emailExists,
            'response' => __($emailExists ? "Email already exists" : "Email Available!")
        ]);
    }

    /**
     * Check duplicate phone number during registration
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkDuplicatePhoneNumber(Request $request)
    {
        $phoneExists = User::where("formattedPhone", $request->phone)
                ->orWhere("phone", $request->phone)
                ->orWhere("phone1", $request->phone)
                ->orWhere("phone2", $request->phone)
                ->orWhere("phone3", $request->phone)
                ->exists();
        return $this->successResponse([
            'status' => $phoneExists,
            'response' => __($phoneExists ? "This phone is already taken" : "Number is ready to register")
        ]);
    }

    /**
     * User Registration
     *
     * @param UserStoreRequest $request
     * @return JsonResponse
    */
    public function registration(UserStoreRequest $request)
    {
        try {
            return $this->successResponse([
                $this->service->userRegistration($request)
            ]);
        } catch (RegistrationException $exception) {
            return $this->unprocessableResponse([], $exception->getMessage());
        } catch (\Exception $exception) {
            return $this->unprocessableResponse([], __("Failed to process the request."));
        }

    }


}
