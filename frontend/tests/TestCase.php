<?php

namespace Tests;

use App\Http\Middleware\EnsureApiAdmin;
use App\Http\Middleware\EnsureApiAuthenticated;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware([
            EnsureApiAuthenticated::class,
            EnsureApiAdmin::class,
        ]);
    }
}
