<?php namespace AppUser\UserApi\Http\Controllers;

use Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use AppUser\UserApi\Classes\UserApiHook;
use AppApi\ApiResponse\Resources\ApiResource;
use AppApi\ApiException\Exceptions\ForbiddenException;
use AppApi\ApiException\Exceptions\UnauthorizedException;

class VerifyEmailResendApiController extends UserApiController
{
    /**
     * Verify email resend.
     */
    public function handle()
    {
        $user = Auth::user();

        if (!$user) {
            throw new UnauthorizedException('User is not authenticated');
        }

        if ($user->is_guest) {
            throw new ForbiddenException('Guest user cannot resend email verification code');
        }

        // remove email from cache
        Cache::store('file')->forget('email_'.$user->id);

        $email = $user->email;
        Cache::store('file')->put('email_'.$user->id, $email, now()->addMinutes(config('appuser.userapi::email_verification_code_expiration_time')));

        $emailVerificationCode = rand(10000, 99999);
        Cache::store('file')->put('email_verification_'.$user->id, $emailVerificationCode, now()->addMinutes(config('appuser.userapi::email_verification_code_expiration_time')));
        $user->email = $user->getOriginal('email');

        $isSent = Event::fire('appuser.userapi.sendEmailVerificationCode', [$user, $email, $emailVerificationCode], true);

        if (!$isSent) {
            Mail::send('appuser.userapi::mail.user_send_email_verification_code', ['code' => $emailVerificationCode], function ($message) use ($email) {
                $message->to($email);
            });
        }

        return $afterProcess = UserApiHook::hook(
            'afterProcess',
            [$this],
            function () use ($emailVerificationCode) {
                return ApiResource::success(
                    data: config('app.debug') ? ['email_verification_code' => $emailVerificationCode] : null
                );
            }
        );
    }
}
