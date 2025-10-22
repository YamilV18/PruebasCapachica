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
        // Agregar nuevos campos a la tabla plans
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('duracion_dias')->default(1)->after('capacidad');
            $table->text('que_incluye')->nullable()->after('descripcion');
            $table->string('imagen_principal')->nullable()->after('que_incluye');
            $table->json('imagenes_galeria')->nullable()->after('imagen_principal');
            $table->unsignedBigInteger('emprendedor_id')->nullable()->after('creado_por_usuario_id');
            $table->decimal('precio_total', 10, 2)->nullable()->after('emprendedor_id');
            $table->enum('dificultad', ['facil', 'moderado', 'dificil'])->default('facil')->after('precio_total');
            $table->text('requerimientos')->nullable()->after('dificultad');
            $table->text('que_llevar')->nullable()->after('requerimientos');
            
            // Agregar índices
            $table->index('emprendedor_id');
            $table->index(['estado', 'es_publico']);
            
            // Agregar foreign key
            $table->foreign('emprendedor_id')->references('id')->on('emprendedores')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropForeign(['emprendedor_id']);
            $table->dropIndex(['emprendedor_id']);
            $table->dropIndex(['estado', 'es_publico']);
            
            $table->dropColumn([
                'duracion_dias',
                'que_incluye',
                'imagen_principal',
                'imagenes_galeria',
                'emprendedor_id',
                'precio_total',
                'dificultad',
                'requerimientos',
                'que_llevar'
            ]);
        });
    }
};