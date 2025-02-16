<?php namespace AppUser\UserApi\Http\Controllers;

use Exception;
use Illuminate\Http\Response;
use RainLab\User\Facades\Auth;
use AppUser\UserApi\Models\User;
use AppUser\UserApi\Facades\JWTAuth;
use Illuminate\Support\Facades\Event;
use AppUser\UserApi\Classes\UserApiHook;
use AppApi\ApiResponse\Resources\ApiResource;
use RainLab\User\Models\Settings as UserSettings;
use AppUser\UserApi\Classes\Services\UserSignupActivationService;

class SignupApiController extends UserApiController
{
    /**
     * Sign up user
     */
    public function handle()
    {
        $data = post();

        if (!array_key_exists('password_confirmation', $data)) {
            $data['password_confirmation'] = post('password');
        }

        $user = $this->registerUser($data);

        if (is_a($user, 'Illuminate\Http\Response')) {
            return $user;
        }

        $token = null;
        if ($user->is_activated) {
            $token = JWTAuth::fromUser($user);
        }

        Event::fire('appuser.userapi.beforeReturnUser', [$user]);

        $userResourceClass = config('appuser.userapi::resources.user');
        $response = [
            'token' => $token,
            'user' => new $userResourceClass($user),
        ];

        return $afterProcess = UserApiHook::hook('afterProcess', [$this, $response], function () use ($response) {
            return ApiResource::success(data: $response)->response()->setStatusCode(Response::HTTP_CREATED);
        });
    }

    protected function registerUser($data)
    {
        Event::fire('rainlab.user.beforeRegister', [&$data]);

        $automaticActivation = UserSettings::get('activate_mode') == UserSettings::ACTIVATE_AUTO;
        $userActivation = UserSettings::get('activate_mode') == UserSettings::ACTIVATE_USER;

        try {
            $user = Auth::register($data, $automaticActivation);
        } catch (Exception $e) {
            $user = User::where('email', $data['email'])->where('is_activated', false)->first();

            // if ($userActivation && $user) {
            //     (new UserSignupActivationService($user))->sendActivationCode();
            //     $userResourceClass = config('appuser.userapi::resources.user');
            //     $response = [
            //         'user' => new $userResourceClass($user),
            //         'token' => null,
            //         'message' => 'Email with account activation code was already sent, check your email',
            //     ];
            //     return response([
            //         'data' => $response,
            //         'status' =>  201
            //     ], 200);
            // }
            throw $e;
        }

        Event::fire('rainlab.user.register', [$user, $data]);

        if ($userActivation && !$user->is_activated) {
            (new UserSignupActivationService($user))->sendActivationCode();
        }

        return $user;
    }

    protected function loginAttribute()
    {
        return UserSettings::get('login_attribute', UserSettings::LOGIN_EMAIL);
    }
}
