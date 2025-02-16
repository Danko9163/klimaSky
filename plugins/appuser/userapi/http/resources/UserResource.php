<?php namespace AppUser\UserApi\Http\Resources;

use Cache;
use October\Rain\Support\Facades\Event;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'last_login' => $this->last_login,
            'last_seen' => $this->last_seen,
            'activated_at' => $this->activated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'is_activated' => (bool) $this->is_activated,
            'is_guest' => (bool) $this->is_guest,
            'is_superuser' => (bool) $this->is_superuser
        ];
        if (config('app.debug')) {
            $data['activation_code'] = $this->activation_code;
            $data['reset_password_code'] = Cache::store('file')->get('reset_code_'.$this->id);
            $data['email_verification_code'] = Cache::store('file')->get('email_verification_'.$this->id);
        }

		Event::fire('appuser.userapi.user.beforeReturnResource', [&$data, $this->resource]);

		return $data;
    }
}
