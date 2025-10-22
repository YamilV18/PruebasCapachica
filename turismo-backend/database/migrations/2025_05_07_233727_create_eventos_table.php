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
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion');
            $table->string('tipo_evento');
            $table->string('idioma_principal');
            $table->date('fecha_inicio');
            $table->time('hora_inicio');
            $table->date('fecha_fin');
            $table->time('hora_fin');
            $table->integer('duracion_horas');
            $table->decimal('coordenada_x', 10, 6);
            $table->decimal('coordenada_y', 10, 6);
            $table->unsignedBigInteger('id_emprendedor');
            $table->text('que_llevar')->nullable();
            $table->timestamps();

            $table->foreign('id_emprendedor')->references('id')->on('emprendedores')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
