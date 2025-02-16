<?php namespace AppUser\UserApi\Http\Controllers;

use Cache;
use RainLab\User\Models\User;
use AppUser\UserApi\Classes\UserApiHook;
use Illuminate\Support\Facades\Validator;
use AppApi\ApiResponse\Resources\ApiResource;
use October\Rain\Exception\ValidationException;
use AppApi\ApiException\Exceptions\BadRequestException;

class ResetApiController extends UserApiController
{
    /**
     * Password reset.
     */
    public function handle()
    {
        $response = [];

        $params = [
            'email' => input('email'),
            'code' => input('code'),
            'password' => input('password'),
            'password_confirmation' => input('password_confirmation') ?? input('password')
        ];

        $validation = Validator::make($params, [
            'email' => 'required|email',
            'code' => 'required',
            'password' => sprintf('required|between:%d,255|confirmed', User::getMinPasswordLength())
        ]);

        if ($validation->fails()) {
            throw new ValidationException($validation);
        }

        $user = User::where('email', $params['email'])->first();

        if ($user) {
			// get reser code from cache
			$resetCode = Cache::store('file')->get('reset_code_'.$user->id);
			if (!$resetCode) {
				throw new BadRequestException('Reset code not found');
			}

			if ($resetCode != $params['code']) {
				throw new BadRequestException('Invalid reset code');
			}

			$user->password = $params['password'];
			$user->password_confirmation = $params['password_confirmation'];
			$user->save();

			Cache::store('file')->forget('reset_code_'.$user->id);
        }

        return $afterProcess = UserApiHook::hook('afterProcess', [$this, $response], function () {
            return ApiResource::success();
        });
    }
}
