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
        Schema::create('reserva_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained()->onDelete('cascade');
            $table->foreignId('emprendedor_id')->constrained('emprendedores')->onDelete('cascade');
            $table->string('descripcion')->nullable();
            $table->integer('cantidad')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reserva_detalle');
    }
};
