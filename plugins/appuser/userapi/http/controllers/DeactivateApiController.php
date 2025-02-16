<?php namespace AppUser\UserApi\Http\Controllers;

use AppUser\UserApi\Facades\JWTAuth;
use Illuminate\Support\Facades\Event;
use AppUser\UserApi\Classes\UserApiHook;
use AppApi\ApiResponse\Resources\ApiResource;

class DeactivateApiController extends UserApiController
{
    /**
     * Deactivate user
     */
    public function handle()
    {
        $response = [];

        $user = JWTAuth::getUser();

        Event::fire('appuser.userapi.beforeDeactivate', [$user]);

        $user->delete();
        Event::fire('appuser.userapi.afterDeactivate', [$user]);

        Event::fire('appuser.userapi.beforeReturnUser', [$user]);

        $userResourceClass = config('appuser.userapi::resources.user');
        $response = [
            'user' => new $userResourceClass($user)
        ];

        return $afterProcess = UserApiHook::hook('afterProcess', [$this, $response], function () use ($response) {
            return ApiResource::success(data: $response);
        });
    }
}
