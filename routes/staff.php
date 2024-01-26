<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;


// Routes Handling Staff app without middleware
// Route::group([], function () {
    Route::get('/', 'StaffController@showLoginForm')->name('staff.login');
    Route::post('staff/authenticate', 'StaffController@login');
    Route::get('staff/dashboard', 'StaffController@dashboard')->name('staff.dashboard');
// });

