<?php namespace AppUser\UserApi\Http\Resources;

use October\Rain\Support\Facades\Event;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSimpleResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'surname' => $this->surname,
            'username' => $this->username,
            'email' => $this->email
        ];

		Event::fire('appuser.userapi.user.beforeReturnSimpleResource', [&$data, $this->resource]);

		return $data;
    }
}
