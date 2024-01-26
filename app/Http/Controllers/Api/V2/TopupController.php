<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\{
  TopupOperator,
  TopupProduct,
  TopupPackage
};

class TopupController extends Controller
{
    public function operators() {
        $operators = TopupOperator::all('id', 'name', 'logo');
        return response()->json([
            "response" => [
                "status"    => ["code" => 200, "message" => "OK"],
                "records"   => $operators
            ]
        ], 200);
    }
    
    public function products(Request $request) {
        $products = TopupProduct::where('operator_id', $request->operator_id)->select('id', 'name')->get();
        return response()->json([
            "response" => [
                "status"    => ["code" => 200, "message" => "OK"],
                "records"   => $products
            ]
        ], 200);
    }
    
    public function packages(Request $request) {
        $packages = TopupPackage::where('product_id', $request->product_id)->select('id', 'description', 'amount')->get();
        return response()->json([
            "response" => [
                "status"    => ["code" => 200, "message" => "OK"],
                "records"   => $packages
            ]
        ], 200);
    }
    
    public function purchase(Request $request) {
        return response()->json([
            "response" => [
                "status"    => ["code" => 200, "message" => "Success"],
            ]
        ], 200);
    }
}