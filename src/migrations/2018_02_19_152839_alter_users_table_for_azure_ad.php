<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterUsersTableForAzureAd extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Users must be able to support blank passwords for external identity
            $table->string('password')->nullable()->change();
            // We need a new string field to store the oauth provider unique id in
            $table->string('azure_id', 36);
            // We need a new string field to store the user principal name in
            $table->string('userPrincipalName');
        });
        // We dont support password resets because social identity is external
        Schema::dropIfExists('password_resets');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // This change is not reversible
    }
}
