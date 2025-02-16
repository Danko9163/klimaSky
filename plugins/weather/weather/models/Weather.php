<?php namespace Weather\Weather\Models;

use Model;

/**
 * Weather Model
 *
 * @link https://docs.octobercms.com/3.x/extend/system/models.html
 */
class Weather extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table name
     */
    public $table = 'weather_weather_weather';

    protected $fillable = [
        'date',
        'temperature',
        'city',
        'description',
        'wind_speed',
        'wind_direction',
        'wind_degree',
        'precipitation',
        'humidity',
    ];

    public $rules = [];
}
