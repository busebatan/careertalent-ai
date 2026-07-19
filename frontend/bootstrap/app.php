<?php

use App\Http\Middleware\EnsureApiAdmin;
use App\Http\Middleware\EnsureApiAuthenticated;
use App\Http\Middleware\EnsureApiCandidate;
use App\Http\Middleware\EnsureApiCompany;
use App\Http\Middleware\SetMarketingLocale;
use App\Http\Middleware\SetPanelLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.api' => EnsureApiAuthenticated::class,
            'auth.api.admin' => EnsureApiAdmin::class,
            'auth.api.company' => EnsureApiCompany::class,
            'auth.api.candidate' => EnsureApiCandidate::class,
            'panel.locale' => SetPanelLocale::class,
            'marketing.locale' => SetMarketingLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
