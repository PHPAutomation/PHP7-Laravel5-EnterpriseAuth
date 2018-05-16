<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAzureAppsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('azure_apps', function (Blueprint $table) {
            // Auto increment for unique id
            $table->increments('id');
            // Some human readable name
            $table->string('name');
            // The app id aaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee
            $table->string('app_id', 36);
            // lets give it timestamps for fun
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('azure_apps');
    }
}
