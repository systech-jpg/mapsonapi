<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Aplikasi diakses lewat tunnel HTTPS (ngrok) yang meneruskan ke
        // http://mapsonapi.test. Tanpa ini Laravel tidak membaca header
        // X-Forwarded-Proto, sehingga route()/redirect() menghasilkan URL http://
        // dan service worker menolak register karena konteksnya dianggap tidak aman.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'dolibarr.auth' => \App\Http\Middleware\CheckDolibarrApiKey::class,
            'api.auth' => \App\Http\Middleware\EnsureApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handler di bawah hanya berlaku untuk request ke /api/*, yaitu surface
        // yang dipakai aplikasi Android. Halaman web/PWA harus tetap memakai
        // response HTML bawaan Laravel, supaya redirect + $errors pada form
        // berfungsi normal.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Route atau Endpoint tidak terdaftar.'
            ], 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Method tidak diizinkan untuk Route ini.'
            ], 405);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        });
    })->create();
