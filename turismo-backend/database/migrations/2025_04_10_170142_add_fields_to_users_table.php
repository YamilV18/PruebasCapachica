<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {

            
            // Add new columns with proper types
            $table->string('country')->nullable();
            $table->date('birth_date')->nullable(); // Changed to date type
            $table->string('address')->nullable();
            $table->string('gender')->nullable();
            $table->string('phone')->nullable(); // Keep this as is
            $table->string('preferred_language')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_login')->nullable(); // Changed to timestamp
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'country', 'birth_date', 'address', 'gender', 
                'phone', 'preferred_language', 'active', 'last_login'
            ]);
            
            // If you're removing first_name and last_name, add them back in down()
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });
    }
};