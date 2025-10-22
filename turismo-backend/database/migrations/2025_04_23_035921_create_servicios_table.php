<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // database/migrations/xxxx_xx_xx_create_servicios_table.php
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('precio_referencial', 10, 2)->nullable();
            $table->foreignId('emprendedor_id')->constrained('emprendedores')->onDelete('cascade');
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
        Schema::table('servicios', function (Blueprint $table) {
            $table->integer('capacidad')->default(1)->after('estado');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};
