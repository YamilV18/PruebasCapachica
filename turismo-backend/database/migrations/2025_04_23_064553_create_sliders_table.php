<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sliders', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('nombre');
            $table->boolean('es_principal')->default(false);
            $table->string('tipo_entidad'); // 'municipalidad', 'emprendedor', 'servicio'
            $table->unsignedBigInteger('entidad_id');
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            
            $table->index(['tipo_entidad', 'entidad_id']);
        });

        Schema::create('slider_descripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slider_id')->constrained()->onDelete('cascade');
            $table->string('titulo')->nullable();
            $table->text('descripcion');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slider_descripciones');
        Schema::dropIfExists('sliders');
    }
};