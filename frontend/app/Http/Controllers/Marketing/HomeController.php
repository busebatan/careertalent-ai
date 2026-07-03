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

    public function careers()
    {
        return view('marketing.careers', [
            'careersCatalog' => \App\Data\MarketingCareersData::forLocale(app()->getLocale()),
            'careersWizardLabels' => [
                'select_main' => __('marketing.careers.select_main'),
                'select_current' => __('marketing.careers.select_current'),
                'select_salary' => __('marketing.careers.select_salary'),
                'empty_options' => __('marketing.careers.empty_options'),
                'pick_main_first' => __('marketing.careers.pick_main_first'),
                'pick_current_first' => __('marketing.careers.pick_current_first'),
                'no_targets_selected' => __('marketing.careers.no_targets_selected'),
                'targets_optional' => __('marketing.careers.targets_optional'),
                'current_label' => __('marketing.careers.current_label'),
                'salary' => __('marketing.careers.salary_label'),
                'radar_subtitle' => __('marketing.careers.radar_subtitle'),
                'target_profile' => __('marketing.careers.target_profile'),
                'skill_schema' => __('marketing.careers.skill_schema'),
            ],
        ]);
    }

    public function pricing()
    {
        return view('marketing.pricing');
    }

    public function gallery()
    {
        return view('marketing.gallery');
    }

    public function faq()
    {
        return view('marketing.faq');
    }

    public function blog()
    {
        return view('marketing.blog');
    }

    public function about()
    {
        return view('marketing.about');
    }

    public function contact()
    {
        return view('marketing.contact');
    }
}
