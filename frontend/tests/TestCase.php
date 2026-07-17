<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.careertalent.api_url', 'http://localhost:8000');
        $this->withoutVite();
        $this->withoutMiddleware();
    }
}
