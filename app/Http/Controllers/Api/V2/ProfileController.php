<?php

/**
 * @package ProfileController
 * @author tehcvillage <support@techvill.org>
 * @contributor Md Abdur Rahaman <[abdur.techvill@gmail.com]>
 * @created 05-12-2022
 */

namespace App\Http\Controllers\Api\V2;

use Illuminate\Support\Facades\Validator;
use App\Exceptions\Api\V2\{
    UserProfileException,
    LoginException,
    WalletException
};
use Illuminate\Http\{
    Request,
    JsonResponse
    };
use App\Rules\CheckValidFile;
use App\Http\Requests\{
    CheckUserDuplicatePhoneNumberRequest,
    UpdatePasswordRequest,
    UploadUserProfilePictureRequest
};
use App\Services\{
    UserProfileService,
    WalletService
};
use App\Models\{
    Wallet,
    User,
    File,
    DocumentVerification
};
use Exception, DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserProfileResource;

/**
 * @group  User Profile
 *
 * API to manage user profile
 */
class ProfileController extends Controller
{
    /**
     * Show User Profile summary
     *
     * @param UserProfileService $service
     * @return JsonResponse
     * @throws LoginException
     */
    public function summary(UserProfileService $service)
    {
        try {
            $user = $service->getProfileSummary(auth()->id());
            return $this->successResponse(new UserProfileResource($user));
        } catch (LoginException $e) {
            return $this->unprocessableResponse([], $e->getMessage());
        } catch (Exception $e) {
            return $this->unprocessableResponse([], __("Failed to process the request."));
        }
    }

    /**
     * Show User Profile details
     *
     * @param UserProfileService $service
     * @return JsonResponse
     * @throws LoginException
     */
    public function details(UserProfileService $service)
    {
        try {
            $userDetails = $service->getProfileDetails(auth()->id());
            return $this->successResponse(new UserProfileResource($userDetails));
        } catch (LoginException $e) {
            return $this->unprocessableResponse([], $e->getMessage());
        } catch (Exception $e) {
            return $this->unprocessableResponse([], __("Failed to process the request."));
        }
    }

    /**
     * Update User Profile informatpion
     *
     * @param Request $request
     * @param UserProfileService $service
     * @return JsonResponse
     * @throws Exception
     */
    public function update(Request $request, UserProfileService $service)
    {
        try {
            DB::beginTransaction();
            $userId = auth()->id();
            $userInfo = $request->only('first_name', 'last_name');
            $userDetailInfo = $request->only('country_id', 'address_1', 'address_2', 'city', 'state', 'timezone');

            if (!empty($request->defaultCountry) && !empty($request->carrierCode)) {
                $userPhoneInfo = $request->only('phone', 'defaultCountry', 'carrierCode');
                $service->phoneUpdate($userId, $userPhoneInfo);
            }

            $service->updateProfileInformation($userId, $userInfo, $userDetailInfo);
            $defaultWallet = $request->default_wallet;
            (new WalletService())->changeDefaultWallet($userId, $defaultWallet);
            DB::commit();
            return $this->okResponse();
        } catch (Exception $e) {
            DB::rollBack();
            return $this->unprocessableResponse([], __($e->getMessage()));
        }
    }

    /**
     * Change User Profile Picture
     *
     * @param UploadUserProfilePictureRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function uploadImage(UploadUserProfilePictureRequest $request, UserProfileService $service)
    {
        try {
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $response = $service->uploadImage(auth()->id(), $image);
            }
            if (true === $response['status']) {
                return $this->okResponse([], $response['message']);
            }
            return $this->unprocessableResponse([], $response['message']);
        } catch (UserProfileException $e) {
            return $this->unprocessableResponse([], $e->getMessage());
        } catch (Exception $e) {
            return $this->unprocessableResponse([], __("Failed to process the request."));
        }
    }

    /**
     * Change User Password
     *
     * @param UpdatePasswordRequest $request
     * @param UserProfileService $service
     * @return JsonResponse
     * @throws UserProfileException
     */
    public function changePassword(UpdatePasswordRequest $request, UserProfileService $service)
    {
        try {
            $oldPassword = $request->old_password;
            $password = $request->password;
            $response = $service->changePassword(auth()->id(), $oldPassword, $password);
            return $this->okResponse([], $response['message']);
        } catch (UserProfileException $e) {
            return $this->unprocessableResponse([], $e->getMessage());
        } catch (Exception $e) {
            return $this->unprocessableResponse([], __("Failed to process the request."));
        }
    }

    /**
     * Get default Wallet balance
     *
     * @param WalletService $service
     * @throws WalletException
     * @return JsonResponse
     */
    public function getDefaultWalletBalance(WalletService $service)
    {
        try {
            return $this->okResponse($service->defaultWalletBalance(auth()->id()));
        } catch (WalletException $e) {
            return $this->unprocessableResponse([], $e->getMessage());
        } catch (Exception $e) {
            return $this->unprocessableResponse([], __("Failed to process the request."));
        }
    }

    /**
     * Get user's all available wallet balances
     *
     * @return JsonResponse
     * @throws WalletException
     */
    public function getUserAvailableWalletsBalance()
    {
        try {
            $wallet = new Wallet();
            $wallets = $wallet->getAvailableBalance(auth()->id());
            if (!$wallets) {
                throw new WalletException(__("No :X found.", ["X" => __("Wallet")]));
            }
            return $this->okResponse($wallets);
        } catch (WalletException $e) {
            return $this->unprocessableResponse([], $e->getMessage());
        } catch (Exception $e) {
            return $this->unprocessableResponse([], __("Failed to process the request."));
        }
    }

    /**
     * Check current user's status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkUserStatus()
    {
        return $this->okResponse(['status' => User::where(['id' => auth()->id()])->value('status')]);
    }
    
    //fcm

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function update_fcm_token(Request $request): JsonResponse
    {
     
        $validator = Validator::make($request->all(), [
            'token' => 'required'
        ]);
        
        
        if ($validator->fails()) {
            return response()->json(['errors' => 'token cannot be empty'], 403);
        }

        $user = User::find($request->userid);

        if(isset($user)) {
            $user->fcm_token = $request->token;
            $user->save();
            return response()->json(['message' => 'FCM token successfully updated'], 200);

        } else {
            return response()->json(['message' => 'User not found'], 404);
        }
    }
    

    /**
     * Check Duplicate phone number when updating phone
     *
     *
     * @return JsonResponse
     */
    public function checkDuplicatePhoneNumber(CheckUserDuplicatePhoneNumberRequest $request)
    {
        return $this->successResponse([
            'status' => true,
            'success' => __("The phone number is Available!")
        ]);
    }
    
    public function logout(Request $request) {
        return $this->successResponse([
            'status' => auth()->user()->token()->delete() == true,
            'success' => __("Logout successful")
        ]);
    }
    
    //Personal Identity Verification - start
    public function updatePersonalId(Request $request)
    {
            $user = User::find(auth()->user()->id);
            $user->identity_verified = false;
            $user->save();
        
            $this->validate($request, [
                'identity_type' => 'required',
                'identity_number' => 'required',
                'identity_file' => ['nullable', new CheckValidFile(getFileExtensions(8))],
            ]);

            $oldFileName = File::where('id', $request->existingIdentityFileID)->value('filename');

            $fileId = $this->insertUserIdentityInfoToFilesTable($request->identity_file);
            if ($fileId && $oldFileName != null) {
                $location = public_path('uploads/user-documents/identity-proof-files/' . $oldFileName);
                if (file_exists($location)) {
                    unlink($location);
                }
            }
        
            $documentVerification = DocumentVerification::where(['user_id' => auth()->user()->id, 'verification_type' => 'identity'])->first();

            if (empty($documentVerification)) {
                $createDocumentVerification = new DocumentVerification();
                $createDocumentVerification->user_id = auth()->user()->id;
                if (!empty($request->identity_file)) {
                    $createDocumentVerification->file_id = $fileId;
                }
                $createDocumentVerification->verification_type = 'identity';
                $createDocumentVerification->identity_type = $request->identity_type;
                $createDocumentVerification->identity_number = $request->identity_number;
                $createDocumentVerification->status = 'pending';
                $createDocumentVerification->save();
            } else {
                $documentVerification->user_id = auth()->user()->id;
                if (!empty($request->identity_file)) {
                    $documentVerification->file_id = $fileId;
                }
                $documentVerification->verification_type = 'identity';
                $documentVerification->identity_type = $request->identity_type;
                $documentVerification->identity_number = $request->identity_number;
                $documentVerification->status = 'pending';
                $documentVerification->save();
            }
        return $this->successResponse([
            'status' => true,
            'success' => __("User Identity Updated Successfully")
        ]);
    }
    
    protected function insertUserIdentityInfoToFilesTable($identity_file)
    {
        if (!empty($identity_file)) {
            $request = app(\Illuminate\Http\Request::class);
            if ($request->hasFile('identity_file')) {
                $fileName = $request->file('identity_file');
                $originalName = $fileName->getClientOriginalName();
                $uniqueName = strtolower(time() . '.' . $fileName->getClientOriginalExtension());
                $file_extn = strtolower($fileName->getClientOriginalExtension());

                if ($file_extn == 'pdf' || $file_extn == 'png' || $file_extn == 'jpg' || $file_extn == 'jpeg' || $file_extn == 'gif' || $file_extn == 'bmp') {
                    $path = 'uploads/user-documents/identity-proof-files';
                    $uploadPath = public_path($path);
                    $fileName->move($uploadPath, $uniqueName);

                    if (isset($request->existingIdentityFileID)) {
                        $checkExistingFile               = File::where(['id' => $request->existingIdentityFileID])->first();
                        $checkExistingFile->filename     = $uniqueName;
                        $checkExistingFile->originalname = $originalName;
                        $checkExistingFile->save();
                        return $checkExistingFile->id;
                    } else {
                        $file               = new File();
                        $file->user_id      = auth()->user()->id;
                        $file->filename     = $uniqueName;
                        $file->originalname = $originalName;
                        $file->type         = $file_extn;
                        $file->save();
                        return $file->id;
                    }
                } else {
                    $this->helper->one_time_message('error', __('Invalid File Format!'));
                }
            }
        }
    }
    //Personal Identity Verification - end
    
    //Personal Address Verification - start
    public function updatePersonalAddress(Request $request)
    {
            $user = User::find(auth()->user()->id, ['id', 'address_verified']);
            $user->address_verified = false;
            $user->save();

            $this->validate($request, [
                'address_file' => ['nullable', new CheckValidFile(getFileExtensions(8))]
            ]);

            $oldFileName = File::where('id', $request->existingAddressFileID)->value('filename');

            $addressFileId = $this->insertUserAddressProofToFilesTable($request->address_file);
            if ($addressFileId && $oldFileName != null) {
                $location = public_path('uploads/user-documents/address-proof-files/' . $oldFileName);
                if (file_exists($location)) {
                    unlink($location);
                }
            }

            $documentVerification = DocumentVerification::where(['user_id' => auth()->user()->id, 'verification_type' => 'address'])->first();
            if (empty($documentVerification)) {
                $createDocumentVerification = new DocumentVerification();
                $createDocumentVerification->user_id =auth()->user()->id;
                if (!empty($request->address_file)) {
                    $createDocumentVerification->file_id = $addressFileId;
                }
                $createDocumentVerification->verification_type = 'address';
                $createDocumentVerification->status            = 'pending';
                $createDocumentVerification->save();
            } else {
                $documentVerification->user_id = auth()->user()->id;
                if (!empty($request->address_file)) {
                    $documentVerification->file_id = $addressFileId;
                }
                $documentVerification->status = 'pending';
                $documentVerification->save();
            }
        return $this->successResponse([
            'status' => true,
            'success' => __("User Address Poof Updated Successfully")
        ]);
    }

    protected function insertUserAddressProofToFilesTable($address_file)
    {
        if (!empty($address_file)) {
            $request = app(\Illuminate\Http\Request::class);
            if ($request->hasFile('address_file')) {
                $fileName     = $request->file('address_file');
                $originalName = $fileName->getClientOriginalName();
                $uniqueName   = strtolower(time() . '.' . $fileName->getClientOriginalExtension());
                $file_extn    = strtolower($fileName->getClientOriginalExtension());

                if ($file_extn == 'pdf' || $file_extn == 'png' || $file_extn == 'jpg' || $file_extn == 'jpeg' || $file_extn == 'gif' || $file_extn == 'bmp') {
                    $path       = 'uploads/user-documents/address-proof-files';
                    $uploadPath = public_path($path);
                    $fileName->move($uploadPath, $uniqueName);

                    if (isset($request->existingAddressFileID)) {
                        $checkExistingFile = File::where(['id' => $request->existingAddressFileID])->first();
                        $checkExistingFile->filename     = $uniqueName;
                        $checkExistingFile->originalname = $originalName;
                        $checkExistingFile->save();
                        return $checkExistingFile->id;
                    } else {
                        $file               = new File();
                        $file->user_id      = auth()->user()->id;
                        $file->filename     = $uniqueName;
                        $file->originalname = $originalName;
                        $file->type         = $file_extn;
                        $file->save();
                        return $file->id;
                    }
                } else {
                    $this->helper->one_time_message('error', __('Invalid File Format!'));
                }
            }
        }
    }
    //Personal Address Verification - end


}
