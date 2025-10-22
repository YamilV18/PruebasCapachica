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
        Schema::table('plan_inscripciones', function (Blueprint $table) {
            $table->datetime('fecha_inscripcion')->nullable();
            $table->datetime('fecha_inicio_plan')->nullable();
            $table->datetime('fecha_fin_plan')->nullable();
            $table->text('notas_usuario')->nullable();
            $table->text('requerimientos_especiales')->nullable();
            $table->integer('numero_participantes')->default(1);
            $table->decimal('precio_pagado', 10, 2)->nullable();
            $table->enum('metodo_pago', ['efectivo', 'transferencia', 'tarjeta', 'yape', 'plin'])->nullable();
            $table->text('comentarios_adicionales')->nullable();

            // Índices
            $table->index('fecha_inscripcion');
            $table->index(['fecha_inicio_plan', 'fecha_fin_plan']);
            $table->index(['estado', 'fecha_inscripcion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_inscripciones', function (Blueprint $table) {
            $table->dropIndex(['fecha_inscripcion']);
            $table->dropIndex(['fecha_inicio_plan', 'fecha_fin_plan']);
            $table->dropIndex(['estado', 'fecha_inscripcion']);

            $table->dropColumn([
                'fecha_inscripcion',
                'fecha_inicio_plan',
                'fecha_fin_plan',
                'notas_usuario',
                'requerimientos_especiales',
                'numero_participantes',
                'precio_pagado',
                'metodo_pago',
                'comentarios_adicionales'
            ]);
        });
    }
};
