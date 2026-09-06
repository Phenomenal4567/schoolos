<?php

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
    // Event auto-discovery is off: listeners in app/Listeners would
    // otherwise be registered a second time on top of the explicit
    // Event::listen() calls in AppServiceProvider::boot() (any listener
    // class with a handle()/__invoke() method type-hinting an event gets
    // auto-wired by convention), which is exactly the double-dispatch
    // this bundle's explicit-wiring convention (see AppServiceProvider's
    // doc comment) is meant to rule out.
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        // 'role' gates a web route group to specific Role::$key values
        // (see EnsureUserHasRole's doc comment). 'scope.checked' is a
        // no-op declarative marker consumed only by
        // Phase1TestGateTest's F22 route-table lint (see
        // MarkScopeChecked's doc comment) — it has no request-handling
        // behavior of its own.
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'scope.checked' => \App\Http\Middleware\MarkScopeChecked::class,
            'timetable.manage' => \App\Http\Middleware\EnsureCanManageTimetable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
