<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function login(): View
    {
        return view('marketing.auth.login');
    }

    public function register(): View
    {
        return view('marketing.auth.register');
    }
}
