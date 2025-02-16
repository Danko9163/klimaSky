<?php

Route::group([
    'prefix' => config('appuser.userapi::routes.prefix'),
    'middleware' => config('appuser.userapi::routes.middlewares', []),
], function () {
    $actions = config('appuser.userapi::routes.actions', []);
    foreach ($actions as $action) {
        $methods = $action['method'];
        if (!is_array($methods)) {
            $methods = [$methods];
        }

        foreach ($methods as $method) {
            Route::{$method}($action['route'], $action['controller'])->middleware($action['middlewares']);
        }
    }
});
