<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;

use App\Models\User;

use App\Services\WalletService;
use App\Services\RegistrationService;

use Illuminate\Http\Request;

class USSDController extends Controller
{  
    public function check_balance(Request $request) {
        try{
            $user = User::where("formattedPhone", $request->customer_phone)
            ->orWhere("phone1", $request->customer_phone)
            ->orWhere("phone2", $request->customer_phone)
            ->orWhere("phone3", $request->customer_phone)
            ->firstOrFail();
            $wallet = (new WalletService)->defaultWalletBalance($user->id);
            $balance = str_replace("USD ", "", $wallet["defaultWalletBalance"]);
            return response()->json(["balance" => $balance]);
        }
        catch(Throwable $e) {
            return response()->json(["success" => false]);
        }
    }
    
    public function get_service_charge() {
        $service_charge = 0;
        return response()->json(["service_charge" => $service_charge]);
    }
    
    public function register_customer(Request $request) {
        try {
            $user = new \stdClass();
            $user->first_name = $request->customer_phone;
            $user->last_name = $request->customer_phone;
            $user->email = $request->customer_phone . "@somxchange.com";
            $user->formattedPhone = $request->customer_phone;
            $user->password = $request->password;
            $user->type = 'user';
            $user->defaultCountry = "so";
            $user->carrierCode = "252";
            $user->phone = str_replace("+252", "", $request->customer_phone);
            
            $response = (new RegistrationService())->userRegistration($user);
            
            return response()->json(["success" => $response["status"]]);
        }
        catch(Throwable $e) {
            return response()->json(["success" => false]);
        }
    }
    
    public function record_transaction(Request $request) {
        try {
            DB::transaction(function () use ($request) {
                $sender = $request->sender;
                $receiver = $request->receiver;
                $reference = $request->reference;
                $amount = $request->amount;
                $rate = $request->rate;
                $fee = $request->rate;
                $platform = $request->platform;
                
                $journal = new Journal();
                $journal->reference = $reference;
                $journal->sender = $sender;
                $journal->receiver = $receiver;
                $journal->amount = $amount;
                $journal->rate = $rate;
                $journal->fee = $fee;
                $journal->save();
                
                
            });
            
            return response()->json(["success" => true]);
        }
        catch(Throwable $e) {
            return response()->json(["success" => false]);
        }
    }
    
    public function payment_received(Request $request) {
        try {
            DB::transaction(function () use ($request) {
                $sender = $request->sender;
                $receiver = $request->receiver;
                $reference = $request->reference;
                $amount = $request->amount;
                $rate = $request->rate;
                $fee = $request->rate;
                $platform = $request->platform;
                
                $ledger = new GeneralLedger();
                // $ledger->coa_id = ;
                // $ledger->reference = ;
                // $ledger->transaction_type = ;
                // $ledger->transaction_object = ;
                // $ledger->memo = ;
                // $ledger->amount = ;
                // $ledger->balance = ;
                
                $journal = new Journal();
                $journal->reference = $reference;
                $journal->sender = $sender;
                $journal->receiver = $receiver;
                $journal->amount = $amount;
                $journal->rate = $rate;
                $journal->fee = $fee;
                $journal->save();
            });
            
            return response()->json(["success" => true]);
        }
        catch(Throwable $e) {
            return response()->json(["success" => false]);
        }
    }
    
    public function change_pin(Request $request) {
        try {
            $user = User::where("formattedPhone", $request->customer_phone)
            ->orWhere("phone1", $request->customer_phone)
            ->orWhere("phone2", $request->customer_phone)
            ->orWhere("phone3", $request->customer_phone)
            ->firstOrFail();
            
            $user->password = \Hash::make($request->password);
            
            $user->save();
            return response()->json(["success" => true]);
        }
        catch(Throwable $e) {
            return response()->json(["success" => false]);
        }
    }
}

