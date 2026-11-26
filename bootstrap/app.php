<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'batch_payment/mandiri-save-export',
            'batch_payment/bca-save-export',
            'batch_payment/bni-save-export',
            'batch_payment/bri-save-export',
            'batch_payment/bsi-save-export',
            'batch_payment/btn-save-export',
            'mandiri-save-export',
            'bca-save-export',
            'bni-save-export',
            'bri-save-export',
            'bsi-save-export',
            'btn-save-export',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
