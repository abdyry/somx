<?php
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function showLoginForm()
    {
        return view('staff.login');
    }

    public function login(Request $request)
    {
        // Handle login logic here
        // You may use Laravel's built-in authentication or implement custom logic
    }

    public function dashboard()
    {
        return view('admin.staff.dashboard');
    }
}
