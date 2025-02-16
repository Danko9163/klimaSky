<?php namespace AppUser\UserApi\Http\Middlewares;

use Closure;
use Tymon\JWTAuth\JWTAuth;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use AppApi\ApiException\Exceptions\UnauthorizedException;

class AuthenticateIfProvided
{
    protected $auth;

    public function __construct(JWTAuth $auth)
    {
        $this->auth = $auth;
    }

    public function checkForToken(Request $request, $next)
    {
        return $this->auth->parser()->setRequest($request)->hasToken();
    }

    public function handle(Request $request, Closure $next)
    {
        if ($this->checkForToken($request, $next)) {
            try {
                if (!$this->auth->parseToken()->authenticate()) {
                    throw new UnauthorizedException('User not found');
                }
            } catch (JWTException $e) {
                throw new UnauthorizedException($e->getMessage());
            }
        }

        return $next($request);
    }
}
