<?php namespace AppUser\UserApi\Http\Controllers;

use AppUser\UserApi\Facades\JWTAuth;
use Illuminate\Support\Facades\Event;
use AppUser\UserApi\Classes\UserApiHook;
use AppApi\ApiResponse\Resources\ApiResource;

class RefreshApiController extends UserApiController
{
    /**
     * Refresh user.
     */
    public function handle()
    {
        $forceRefresh = env('JWT_FORCE_REFRESH', false);

        $oldToken = JWTAuth::getToken();
        $newToken = $forceRefresh ? self::generateNewToken($oldToken) : JWTAuth::parseToken()->refresh();

        Event::fire('appuser.userapi.afterRefresh', [$oldToken, $newToken]);

        $user = JWTAuth::setToken($newToken)->authenticate();

		$user->touchLastSeen();

		$ipAddress = request()->ip();
		if ($ipAddress) {
			$user->touchIpAddress($ipAddress);
		}

		Event::fire('appuser.userapi.beforeReturnUser', [$user]);

        $userResourceClass = config('appuser.userapi::resources.user');
        $response = [
            'token' => $newToken,
            'user' => new $userResourceClass($user),
        ];

        return $afterProcess = UserApiHook::hook('afterProcess', [$this, $response], function () use ($response) {
            return ApiResource::success(data: $response);
        });
    }

    private function generateNewToken($oldToken)
    {
        $newToken = JWTAuth::refresh();
        $user = JWTAuth::setToken($newToken)->toUser();
        JWTAuth::invalidate();

        if (!$user) {
            if (env('LOG_USER_NOT_PARSED_FROM_TOKEN', false)) {
                $tokenData = JWTAuth::manager()->decode($oldToken, $checkBlacklist = false);
                $message = 'User ID:'.$tokenData['sub'].' not parsed from token'.PHP_EOL.'Token Data:'.PHP_EOL.json_encode($tokenData, JSON_PRETTY_PRINT);
                logger()->info($message);
            }

            abort(401, 'User not parsed from token');
        }

        return JWTAuth::fromUser($user);
    }
}
