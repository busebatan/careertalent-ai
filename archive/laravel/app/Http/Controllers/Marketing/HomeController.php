<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    public function index()
    {
        return view('marketing.home');
    }

    public function features()
    {
        return view('marketing.features');
    }

    public function howItWorks()
    {
        return view('marketing.how-it-works');
    }

    public function bootcamp()
    {
        return view('marketing.bootcamp');
    }
}
