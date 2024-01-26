<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhoneOTP extends Model
{
    protected $table = "phone_otp";
    protected $fillable = [
        "phone",
        "otp",
        "expires_at"
    ];
}
