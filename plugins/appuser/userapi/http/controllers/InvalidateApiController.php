<?php namespace AppUser\UserApi\Http\Controllers;

use AppUser\UserApi\Facades\JWTAuth;
use Illuminate\Support\Facades\Event;
use AppUser\UserApi\Classes\UserApiHook;
use AppApi\ApiResponse\Resources\ApiResource;

class InvalidateApiController extends UserApiController
{
    /**
     * Logout user.
     */
    public function handle()
    {
        $response = [];

        $user = JWTAuth::getUser();

        $token = JWTAuth::parseToken()->getToken();
        JWTAuth::invalidate($token);

        Event::fire('appuser.userapi.afterInvalidate', [$user]);

        return $afterProcess = UserApiHook::hook('afterProcess', [$this, $response], function () {
            return ApiResource::success();
        });
    }
}
