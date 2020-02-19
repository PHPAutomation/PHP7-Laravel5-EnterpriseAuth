<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->string('azure_id', 36)
                  ->nullable()
                  ->after('email');
            // We need a new string field to store the user principal name in
            $table->string('userPrincipalName')
                  ->nullable()
                  ->after('azure_id');
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
