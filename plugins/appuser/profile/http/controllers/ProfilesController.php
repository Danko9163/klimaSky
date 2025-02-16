<?php namespace AppUser\Profile\Http\Controllers;

use Illuminate\Http\Request;
use RainLab\User\Models\User;
use Illuminate\Routing\Controller;
use AppUser\UserApi\Facades\JWTAuth;
use October\Rain\Support\Facades\Event;
use AppApi\ApiResponse\Resources\ApiResource;
use AppUser\Profile\Http\Resources\ProfileResource;

class ProfilesController extends Controller
{
    public function __invoke(Request $request, $key)
    {
        $loggedUser = JWTAuth::getUser();

        $user = User::isPublished()
            ->where('id', $key)
            ->orWhere('uuid', $key)
            ->orWhere('username', $key)
            ->firstOrFail();

        if ($loggedUser) {
            Event::fire('appuser.userprofile.action.show', [$user]);
        }

        $response = new ProfileResource($user);

		return ApiResource::success(data: $response);
	}
}
