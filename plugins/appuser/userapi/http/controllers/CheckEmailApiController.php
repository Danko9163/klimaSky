<?php namespace AppUser\UserApi\Http\Controllers;

use Event;
use RainLab\User\Models\User;
use AppApi\ApiResponse\Resources\ApiResource;
use AppApi\ApiException\Exceptions\BadRequestException;

class CheckEmailApiController extends UserApiController
{
    protected $requiredCheck = true;

    /**
     * Check email
     */
    public function handle()
    {
        Event::fire('rainlab.user.beforeCheckEmail', [$this]);

        if ($this->requiredCheck) {
            $this->check();
        }

        return ApiResource::success('Email is available');
    }

    protected function check()
    {
        if (!post('email')) {
            throw new BadRequestException('Email is required');
        }
        if ($user = User::where('email', post('email'))->first()) {
            // if is not ACTIVATED then the email is free(the password is not set)
            if ($user->is_activated) {
                throw new BadRequestException(
                    message: 'Email is already taken',
                    isToast: true
                );
            }
        }
    }
}
