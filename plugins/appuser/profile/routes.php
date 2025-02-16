<?php

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use AppUser\UserApi\Http\Middlewares\Check;
use AppUser\UserApi\Http\Middlewares\Authenticate;

Route::group([
    'prefix'      => 'api/v1',
    'namespace'  => 'AppUser\Profile\Http\Controllers',
    'middleware' => [
        'api',
        Check::class
    ]
], function (Router $router) {
    $router
        ->get('profile/{key}', 'ProfilesController')
        ->middleware([Authenticate::class])
        ->name('profile.show')
        ->name('profile.by_username');
    $router
        ->get('public/profile/{key}', 'ProfilesController')
        ->name('profile.show')
        ->name('profile.by_username');
});
