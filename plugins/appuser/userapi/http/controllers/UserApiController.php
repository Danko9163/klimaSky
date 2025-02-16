<?php namespace AppUser\UserApi\Http\Controllers;

use Exception;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use AppUser\UserApi\Classes\UserApiHook;

abstract class UserApiController extends Controller
{
    abstract public function handle();

    public function __construct()
    {
        Event::fire('appuser.userapi.controllerConstruct', [$this]);
    }

    public function __invoke(Request $request)
    {
        try {
            return UserApiHook::hook('beforeProcess', [$this], function () {
                return $this->handle();
            });
        } catch (Exception $exception) {
            return UserApiHook::hook('beforeReturnException', [$this, $exception], function () use ($exception) {
                throw $exception;
            });
        }
    }
}
