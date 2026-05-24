<?php

use App\Http\Middleware\EnsureOrganizationContext;
use App\Http\Middleware\ForceJsonResponse;
use ErrorException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'organization.context' => EnsureOrganizationContext::class,
        ]);

        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request, Throwable $e) {
            return $request->is('api/*');
        });

        $exceptions->dontReport(static function (Throwable $e): bool {
            return $e instanceof ErrorException
                && str_contains($e->getMessage(), 'Can not authenticate to IMAP server');
        });

        // ext-imap can emit a second error at request shutdown after a failed login,
        // which would append a second JSON body to an otherwise valid API response.
        $exceptions->render(static function (ErrorException $e, Request $request) {
            if (! $request->is('api/*') || ! headers_sent()) {
                return null;
            }

            if (str_contains($e->getMessage(), 'Can not authenticate to IMAP server')) {
                if (function_exists('imap_errors')) {
                    imap_errors();
                    imap_alerts();
                }

                exit(0);
            }

            return null;
        });
    })->create();
