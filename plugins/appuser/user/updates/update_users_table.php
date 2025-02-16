<?php namespace AppUser\UserApi\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
			$table->string('phone_number')->nullable();
			$table->boolean('is_email_verified')->default(false);
			$table->boolean('is_published')->default(true);
			$table->boolean('gdpr_consent')->default(false);
			$table->boolean('newsletter_subscriber')->default(true);
		});
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_number');
			$table->dropColumn('is_email_verified');
			$table->dropColumn('is_published');
			$table->dropColumn('gdpr_consent');
			$table->dropColumn('newsletter_subscriber');
		});
    }
};
