<?php

namespace Tests;

use App\Http\Middleware\EnsureApiAdmin;
use App\Http\Middleware\EnsureApiAuthenticated;
use App\Http\Middleware\EnsureApiCandidate;
use App\Http\Middleware\EnsureApiCompany;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.careertalent.api_url', 'http://localhost:8000');
        $this->withoutVite();
        $this->withoutMiddleware([
            EnsureApiAuthenticated::class,
            EnsureApiAdmin::class,
            EnsureApiCandidate::class,
            EnsureApiCompany::class,
        ]);
    }
}
