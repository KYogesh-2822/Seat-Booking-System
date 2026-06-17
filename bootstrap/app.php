<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'no-cache' => \App\Http\Middleware\NoCache::class,
            'mfa.enabled' => \App\Http\Middleware\RequireEnabledMfa::class,
            'mail.mfa' => \App\Http\Middleware\RequireMailSettingsMfa::class,
            'no.body.logs' => \App\Http\Middleware\DoNotLogRequestBody::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('admin*') || $request->is('events*')) {
                return route('admin.login');
            }
            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
