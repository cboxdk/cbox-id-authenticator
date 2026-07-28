<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The native screens. Loaded through `then` because `Route::native()` is a macro
        // the NativePHP provider registers, and the named route files are resolved
        // before providers boot.
        // No middleware group. The reference app registers native routes bare, and the
        // `web` group's session and CSRF handling has nothing to do on a screen that is
        // rendered to SwiftUI rather than served over HTTP.
        then: function (): void {
            Route::group([], base_path('routes/mobile.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
