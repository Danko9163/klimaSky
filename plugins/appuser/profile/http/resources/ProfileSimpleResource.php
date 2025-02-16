<?php namespace AppUser\Profile\Http\Resources;

use October\Rain\Support\Facades\Event;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileSimpleResource extends JsonResource
{
    public static $avatarWidth = 320;
    public static $avatarHeight = 320;

    public function toArray($request)
    {
        $response = [
            'id'          => $this->id,
            'username'    => $this->nickname,
            'name'        => $this->name,
			'surname'     => $this->surname,
            'avatar'      => [
                'path'         => $this->avatar->getThumb(self::$avatarWidth, self::$avatarHeight, ['mode' => 'crop']),
                'file_name'     => $this->avatar->file_name,
                'content_type' => $this->avatar->content_type
            ]
        ];

		Event::fire('appuser.profile.profile.beforeReturnSimpleResource', [&$response, $this->resource]);

		return $response;
    }
}
