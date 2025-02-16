<?php namespace KlimaSky\Weather\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateWeatherTable Migration
 *
 * @link https://docs.octobercms.com/3.x/extend/database/structure.html
 */
return new class extends Migration
{
    /**
     * up builds the migration
     */
    public function up()
    {
        Schema::create('weather_weather_weather', function(Blueprint $table) {
            $table->increments('id');
            $table->date('date');
            $table->decimal('temperature', 5, 2);
            $table->string('city');
            $table->string('description');
            $table->decimal('wind_speed', 5, 2);
            $table->string('wind_direction');
            $table->integer('wind_degree');
            $table->decimal('precipitation', 5, 2);
            $table->decimal('humidity', 5, 2);
            $table->timestamps();
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('weather_weather_weather');
    }
};
