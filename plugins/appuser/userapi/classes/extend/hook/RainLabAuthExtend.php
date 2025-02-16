<?php namespace AppUser\UserApi\Classes\Extend\Hook;

use Auth;
use Event;
use Illuminate\Support\Facades\Lang;
use AppApi\ApiException\Exceptions\UnauthorizedException;

class RainLabAuthExtend
{
    public static function throwExceptionIfUserIsTrashed()
    {
        Event::listen('rainlab.user.beforeAuthenticate', function ($handler, $credentials) {

            $login = array_get($credentials, 'login');

            /*
             * No such user exists
             */
            if (!$user = Auth::findUserByLogin($login)) {
                return;
            }

            /*
             * Throw exception if user is trashed, otherwise he would be reactivated
             */
            if ($user->trashed()) {
                throw new UnauthorizedException(
                    Lang::get(
                        'appuser.userapi::error.ACCOUNT_DEACTIVATED',
                        ['account' => $login]
                    ),
                    401
                );
            }
        });
    }
}
