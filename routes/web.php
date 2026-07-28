<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/clear', function () {
    \Illuminate\Support\Facades\Artisan::call('route:clear');
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())->map(function ($route) {
        return $route->uri();
    });
    return response()->json([
        'cache_cleared' => true, 
        'opcache_reset' => function_exists('opcache_reset'),
        'routes' => $routes
    ]);
});
