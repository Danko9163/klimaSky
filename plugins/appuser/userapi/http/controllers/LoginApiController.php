<?php namespace AppUser\UserApi\Http\Controllers;

use RainLab\User\Facades\Auth;
use AppUser\UserApi\Facades\JWTAuth;
use Illuminate\Support\Facades\Event;
use AppUser\UserApi\Classes\UserApiHook;
use AppApi\ApiResponse\Resources\ApiResource;
use AppApi\ApiException\Exceptions\ForbiddenException;

class LoginApiController extends UserApiController
{
    /**
     * Login a user.
     */
    public function handle()
    {
        $response = [];

		$params = [
			'login' => input('login'),
			'password' => input('password'),
		];

        $user = Event::fire('appuser.userapi.beforeLogin', [$params], true);

        if ($user) {
            Auth::loginUsingId($user->id);
        } else {
            $user = Auth::authenticate([
                'login' => input('login'),
                'password' => input('password'),
            ], false);
        }

        if ($user->isBanned()) {
            throw new ForbiddenException('rainlab.user::lang.account.banned');
        }

		$user->touchLastSeen();

        $ipAddress = request()->ip();
        if ($ipAddress) {
            $user->touchIpAddress($ipAddress);
        }

        $token = JWTAuth::fromUser($user);

        Event::fire('appuser.userapi.beforeReturnUser', [$user]);

        $userResourceClass = config('appuser.userapi::resources.user');
        $response = [
            'token' => $token,
            'user' => new $userResourceClass($user)
        ];

        return $afterProcess = UserApiHook::hook('afterProcess', [$this, $response], function () use ($response) {
            return ApiResource::success(data: $response);
        });
    }
}
