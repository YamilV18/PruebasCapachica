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
        Schema::create('plan_dias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->integer('numero_dia');
            $table->string('titulo');
            $table->text('descripcion');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->integer('duracion_estimada_minutos')->nullable();
            $table->text('notas_adicionales')->nullable();
            $table->integer('orden')->default(1);
            $table->timestamps();
            
            // Índices
            $table->index('plan_id');
            $table->index(['plan_id', 'numero_dia']);
            $table->index(['plan_id', 'orden']);
            
            // Foreign key
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
            
            // Unique constraint para evitar duplicados
            $table->unique(['plan_id', 'numero_dia']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_dias');
    }
};