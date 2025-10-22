<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipalidad', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion');
            $table->string('red_facebook')->nullable();
            $table->string('red_instagram')->nullable();
            $table->string('red_youtube')->nullable();
            $table->decimal('coordenadas_x', 10, 7)->nullable();
            $table->decimal('coordenadas_y', 10, 7)->nullable();
            $table->text('frase')->nullable();
            $table->text('comunidades')->nullable();
            $table->text('historiafamilias')->nullable();
            $table->text('historiacapachica')->nullable();
            $table->text('comite')->nullable();
            $table->text('mision')->nullable();
            $table->text('vision')->nullable();
            $table->text('valores')->nullable();
            $table->text('ordenanzamunicipal')->nullable();
            $table->text('alianzas')->nullable();
            $table->string('correo')->nullable();
            $table->string('horariodeatencion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalidad');
    }
};