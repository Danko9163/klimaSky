<?php namespace AppUser\UserApi;

use System\Classes\PluginBase;
use Illuminate\Contracts\Cache\Repository;
use Tymon\JWTAuth\Providers\Storage\Illuminate;
use AppUser\UserApi\Providers\AuthServiceProvider;
use AppUser\UserApi\Providers\JWTAuthServiceProvider;
use AppUser\UserApi\Classes\Extend\Hook\RainLabAuthExtend;

/**
 * UserApi Plugin Information File
 */
class Plugin extends PluginBase
{
    public $elevated = true;

    public $require = [
        'AppApi.ApiException',
        'AppApi.ApiResponse',
        'RainLab.User'
    ];

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name' => 'UserApi',
            'description' => 'Auth API for RainLab.User plugin',
            'author' => 'AppUser',
            'icon' => 'icon-key',
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(JWTAuthServiceProvider::class);

		$this->app->config
			->set('cache.stores.jwt', [
				'driver' => 'file',
				'path' => storage_path('framework/jwt'),
			]);

		$this->app
			->when(Illuminate::class)
			->needs(Repository::class)
			->give(function () {
				return cache()->store('jwt');
			});
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
        RainLabAuthExtend::throwExceptionIfUserIsTrashed();
    }

	public function registerMailTemplates()
	{
		return [
			'appuser.userapi::mail.user_send_email_verification_code'
		];
	}
}
