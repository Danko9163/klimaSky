<?php namespace AppUser\UserApi\Http\Controllers;

use Cache;
use Carbon\Carbon;
use RainLab\User\Models\User;
use AppUser\UserApi\Classes\UserApiHook;
use AppApi\ApiResponse\Resources\ApiResource;
use AppApi\ApiException\Exceptions\BadRequestException;
use AppUser\UserApi\Classes\Services\UserForgotPasswordService;

class ForgotApiController extends UserApiController
{
    /**
     * Forgot password.
     */
    public function handle()
    {
        $params = [
            'email' => input('email')
        ];

        $user = User::where('email', $params['email'])->first();

        if ($user) {
			if (!$user->is_activated) {
				throw new BadRequestException('User is not activated');
			}

			$resetPasswordCode = mt_rand(100000, 999999);

			Cache::store('file')->put('reset_code_'.$user->id, $resetPasswordCode, config('appuser.userapi::config.password_reset_code_expiration_time'));

			(new UserForgotPasswordService($user))->sendResetCode();

			$user->reset_password_code = null;
			$user->save();
        }

        return $afterProcess = UserApiHook::hook('afterProcess', [$this], function () use ($user) {
            return ApiResource::success(
               'If your email address exists in our database, you will receive a password recovery link at your email address in few minutes.',
                config('app.debug') ? ['reset_code' => Cache::store('file')->get('reset_code_'.$user->id)] : null
            );
        });
    }
}
