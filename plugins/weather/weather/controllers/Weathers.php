<?php namespace Weather\Weather\Controllers;

use Backend\Behaviors\ListController;
use BackendMenu;
use Backend\Classes\Controller;
use Weather\Weather\Models\Weather;

/**
 * Weathers Backend Controller
 *
 * @link https://docs.octobercms.com/3.x/extend/system/controllers.html
 */
class Weathers extends Controller
{

    public $implement = [
        \Backend\Behaviors\FormController::class,
        ListController::class,
    ];

    /**
     * @var string formConfig file
     */
    public $formConfig = 'config_form.yaml';

    /**
     * @var string listConfig file
     */
    public $listConfig = 'config_list.yaml';

    /**
     * @var array required permissions
     */
    public $requiredPermissions = ['weather.weather.weathers'];

    /**
     * __construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Weather.Weather', 'weather', 'weathers');
    }
}
