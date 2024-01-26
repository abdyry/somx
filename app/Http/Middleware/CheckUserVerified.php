<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class CheckUserVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next, $role = null)
    {
        $identity_verified = auth()->user()->identity_verified;
        $address_verified = auth()->user()->address_verified;
        
        if (!$identity_verified)
        {
            return redirect()->route('user.setting.identitiy_verify');
        }
        
        if (!$address_verified)
        {
            return redirect()->route('user.setting.address_verify');
        }
        return $next($request);
    }
}
