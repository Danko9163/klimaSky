<?php namespace AppUser\UserApi\Http\Controllers;

use Mail;
use Cache;
use System\Models\File;
use RainLab\User\Models\User;
use AppUser\UserApi\Facades\JWTAuth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Event;
use AppUser\UserApi\Classes\UserApiHook;
use AppApi\ApiResponse\Resources\ApiResource;
use October\Rain\Exception\ValidationException;
use AppApi\ApiException\Exceptions\BadRequestException;
use AppApi\ApiException\Exceptions\ForbiddenException;

class InfoUpdateApiController extends UserApiController
{
    /**
     * Update user info.
     */
    public function handle()
    {
        $response = [];

        $data = post();
        $user = JWTAuth::getUser();

        if ($user->is_guest) {
            throw new ForbiddenException('You are not allowed to update user info');
        }
        if (array_key_exists('password', $data) && !$user->checkHashValue('password', array_get($data, 'password_current'))) {
            throw new ValidationException(['password_current' => Lang::get('rainlab.user::lang.account.invalid_current_pass')]);
        }

        $user->fill($data);
        if (array_key_exists('email', $data)) {
            if (User::where('email', $data['email'])->first()) {
                throw new BadRequestException(
                    message: Lang::get('appuser.userapi::error.EMAIL_ALREADY_TAKEN'),
                    isToast: true
                );
            }

            $newEmail = $data['email'];
            $emailVerificationCode = rand(10000, 99999);

            $isSent = Event::fire('appuser.userapi.sendEmailVerificationCode', [&$user, $newEmail, $emailVerificationCode]);

            if (!$isSent) {
                Cache::store('file')->put('email_'.$user->id, $newEmail, now()->addMinutes(config('appuser.userapi::email_verification_code_expiration_time')));

                Cache::store('file')->put('email_verification_'.$user->id, $emailVerificationCode, now()->addMinutes(config('appuser.userapi::email_verification_code_expiration_time')));
                $user->email = $user->getOriginal('email');

                Mail::send('appuser.userapi::mail.user_send_email_verification_code', ['code' => $emailVerificationCode], function ($message) use ($newEmail) {
                    $message->to($newEmail)->subject('Overenie emailu');
                });
            }
        }

        if (array_has($data, 'avatar') && empty($data['avatar']) && $user->avatar) {
            $user->avatar->delete();
            $user->avatar = null;
        }

        if (request()->hasFile('avatar')) {
            $file = new File();
            $file->fromPost(request()->file('avatar'));
            $file->save();

            $user->avatar = $file;
        }

        $user->save();

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
