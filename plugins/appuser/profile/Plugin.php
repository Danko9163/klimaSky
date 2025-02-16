<?php namespace AppUser\Profile;

use System\Classes\PluginBase;

/**
 * Profile Plugin Information File
 */
class Plugin extends PluginBase
{
    /*
     * Dependencies
     */
    public $require = [
        'RainLab.User',
        'AppUser.UserApi'
    ];
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Profile',
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
		//
    }
}
