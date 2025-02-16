<?php namespace AppUser\UserApi\Classes\Services;

use Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;

class UserForgotPasswordService
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function sendResetCode()
    {
        $resetPasswordCode = Cache::store('file')->get('reset_code_'.$this->user->id);

        $isSent = Event::fire('appuser.userapi.sendResetPasswordCode', [$this->user, $resetPasswordCode], true);

        if (!$isSent) {
            $data = [
                'name' => $this->user->name,
                'username' => $this->user->username,
                'link' => url(vsprintf('/api/v1/auth/reset-password/?email=%s&code=%s', [
                    $this->user->email,
                    $resetPasswordCode,
                ])),
                'code' => $resetPasswordCode
            ];

            Mail::send('rainlab.user::mail.restore', $data, function ($message) {
                $message->to($this->user->email, $this->user->full_name);
            });
        }
    }
}
