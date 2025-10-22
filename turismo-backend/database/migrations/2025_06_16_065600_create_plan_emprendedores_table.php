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
        // Crear tabla pivote para múltiples emprendedores en un plan
        Schema::create('plan_emprendedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('emprendedor_id')->constrained('emprendedores')->onDelete('cascade');
            $table->enum('rol', ['organizador', 'colaborador'])->default('colaborador');
            $table->boolean('es_organizador_principal')->default(false);
            $table->text('descripcion_participacion')->nullable();
            $table->decimal('porcentaje_ganancia', 5, 2)->nullable(); // Para dividir ganancias
            $table->timestamps();
            
            // Índices
            $table->unique(['plan_id', 'emprendedor_id']);
            $table->index(['plan_id', 'es_organizador_principal']);
            $table->index('emprendedor_id');
        });
        
        // Migrar datos existentes
        DB::statement('
            INSERT INTO plan_emprendedores (plan_id, emprendedor_id, rol, es_organizador_principal, created_at, updated_at)
            SELECT id, emprendedor_id, \'organizador\', true, created_at, updated_at
            FROM plans 
            WHERE emprendedor_id IS NOT NULL
        ');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_emprendedores');
    }
};