<?php namespace AppUser\User;

use System\Classes\PluginBase;
use AppUser\User\Classes\Extend\UserModelExtend;
use AppUser\User\Classes\Extend\UsersControllerExtend;

/**
 * User Plugin Information File
 */
class Plugin extends PluginBase
{
    /*
     * Dependencies
     */
    public $require = [
        'RainLab.User',
        'AppCommerce.Newsletter'
    ];

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'User',
            'description' => 'No description provided yet...',
            'author'      => 'AppUser',
            'icon'        => 'icon-leaf'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {
		//
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
		UserModelExtend::extend();
		UsersControllerExtend::extend();
    }
}
