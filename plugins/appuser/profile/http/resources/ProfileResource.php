<?php namespace AppUser\Profile\Http\Resources;

use October\Rain\Support\Facades\Event;
use AppAd\Ad\Classes\Enums\AdStatusEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public static $avatarWidth = 320;
    public static $avatarHeight = 320;

    public function toArray($request)
    {
        $response = [
            'id'          => $this->id,
            'username'    => $this->username,
            'name'        => $this->name,
			'surname'     => $this->surname,
            'avatar'      => $this->avatar,
            'ads'         => AdResource::collection(
                $this->ads()->where('status', AdStatusEnum::PUBLISHED->value)->get()
            )
        ];

        Event::fire('appuser.profile.profile.beforeReturnResource', [&$response, $this->resource]);

        return $response;
    }
}
