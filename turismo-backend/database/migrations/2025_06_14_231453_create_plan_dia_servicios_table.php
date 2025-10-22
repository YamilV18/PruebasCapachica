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
        Schema::create('plan_dia_servicios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_dia_id');
            $table->unsignedBigInteger('servicio_id');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->integer('duracion_minutos')->nullable();
            $table->text('notas')->nullable();
            $table->integer('orden')->default(1);
            $table->boolean('es_opcional')->default(false);
            $table->decimal('precio_adicional', 8, 2)->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('plan_dia_id');
            $table->index('servicio_id');
            $table->index(['plan_dia_id', 'orden']);
            
            // Foreign keys
            $table->foreign('plan_dia_id')->references('id')->on('plan_dias')->onDelete('cascade');
            $table->foreign('servicio_id')->references('id')->on('servicios')->onDelete('cascade');
            
            // Unique constraint para evitar duplicados
            $table->unique(['plan_dia_id', 'servicio_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_dia_servicios');
    }
};