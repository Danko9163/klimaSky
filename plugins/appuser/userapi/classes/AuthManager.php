<?php namespace AppUser\UserApi\Classes;

use AppUser\UserApi\Models\User;
use RainLab\User\Classes\AuthManager as AuthManagerBase;

class AuthManager extends AuthManagerBase
{
    protected $userModel = User::class;
}
