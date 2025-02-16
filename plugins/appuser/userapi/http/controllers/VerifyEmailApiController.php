<?php namespace AppUser\UserApi\Http\Controllers;

use Cache;
use RainLab\User\Models\User;
use AppUser\UserApi\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use AppUser\UserApi\Classes\UserApiHook;
use AppApi\ApiResponse\Resources\ApiResource;
use AppApi\ApiException\Exceptions\BadRequestException;

class VerifyEmailApiController extends UserApiController
{
    /**
     * Verify email.
     */
    public function handle()
    {
        $response = [];
        $user = null;

        $params = [
            'code' => input('code'),
            'email' => input('email')
        ];

        if (!isset($params['email'])) {
            $user = JWTAuth::parseToken()->authenticate();
        }

        //$user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            if (!isset($params['code']) && isset($params['email'])) {
                throw new BadRequestException('Code is required');
            }
            $user = User::where('email', $params['email'])->firstOrFail();
            $userId = $user->id;
            $user = Auth::loginUsingId($user->id);
            $token = JWTAuth::fromUser($user);

            // add token to headers
            request()->headers->set('Authorization', 'Bearer '.$token);
            // add token to request headers

        } else {
            if (!isset($params['code'])) {
                throw new BadRequestException('Code is required');
            }
            $user = Auth::loginUsingId($user->id);
			$userId = $user->id;
            $token = JWTAuth::fromUser($user);
            // add token to headers
            request()->headers->set('Authorization', 'Bearer '.$token);
        }

        $emailVerificationCode = Cache::store('file')->get('email_verification_'.$userId);

        if ($emailVerificationCode != $params['code']) {
            throw new BadRequestException('Invalid code');
        }

        $verifiedEmail = Cache::store('file')->get('email_'.$userId);
        if (!$verifiedEmail) {
            throw new BadRequestException('Invalid email');
        }

        $user->email = $verifiedEmail;
        $user->is_email_verified = true;
        $user->save();

        Cache::store('file')->forget('email_verification_'.$userId);
        Cache::store('file')->forget('email_'.$userId);

        return $afterProcess = UserApiHook::hook('afterProcess', [$this, $response], function () {
            return ApiResource::success();
        });
    }
}
