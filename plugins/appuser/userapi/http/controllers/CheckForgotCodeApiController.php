<?php namespace AppUser\UserApi\Http\Controllers;

use Cache;
use RainLab\User\Models\User;
use AppUser\UserApi\Classes\UserApiHook;
use Illuminate\Support\Facades\Validator;
use AppApi\ApiResponse\Resources\ApiResource;
use October\Rain\Exception\ValidationException;
use AppApi\ApiException\Exceptions\NotFoundException;
use AppApi\ApiException\Exceptions\BadRequestException;

class CheckForgotCodeApiController extends UserApiController
{
    /**
     * Check forgot code
     */
    public function handle()
    {
        $response = [];

        $params = [
            'email' => input('email'),
            'code' => input('code')
        ];

        $validation = Validator::make($params, [
            'email' => 'required|email',
            'code' => 'required'
        ]);

        if ($validation->fails()) {
            throw new ValidationException($validation);
        }

        $user = User::where('email', $params['email'])->first();

        if (!$user) {
            throw new NotFoundException('User not found');
        }
        // get reset code from cache
        $resetCode = Cache::store('file')->get('reset_code_'.$user->id);
        if (!$resetCode) {
            throw new BadRequestException('Reset code not found');
        }

        if ($resetCode != $params['code']) {
            throw new BadRequestException('Invalid reset code');
        }

        $response = [
            'is_valid' => true
        ];

        return $afterProcess = UserApiHook::hook('afterProcess', [$this, $response], function () use ($response) {
            return ApiResource::success(data: $response);
        });
    }
}
