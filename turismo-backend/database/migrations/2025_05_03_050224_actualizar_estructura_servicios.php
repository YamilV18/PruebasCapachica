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
        // Actualizar la tabla de servicios
        Schema::table('servicios', function (Blueprint $table) {
            // Añadir coordenadas geográficas
            $table->decimal('latitud', 10, 7)->nullable()->after('estado');
            $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
            $table->text('ubicacion_referencia')->nullable()->after('longitud');
        });

        // Crear tabla de horarios disponibles para servicios
        Schema::create('servicio_horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicios')->onDelete('cascade');
            $table->enum('dia_semana', ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo']);
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        
        // Eliminar campos innecesarios y crear una estructura nueva para reservas
        Schema::dropIfExists('reserva_detalle');
        Schema::dropIfExists('reservas');
        
        // Nueva tabla de reservas con estado "en_carrito" incluido
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->string('codigo_reserva')->unique();
            $table->enum('estado', ['en_carrito', 'pendiente', 'confirmada', 'cancelada', 'completada'])->default('pendiente');
            $table->text('notas')->nullable();
            $table->timestamps();
        });
        
        // Crear tabla para los items de la reserva (servicios reservados) con estado "en_carrito" incluido
        Schema::create('reserva_servicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->onDelete('cascade');
            $table->foreignId('servicio_id')->constrained('servicios')->onDelete('cascade');
            $table->foreignId('emprendedor_id')->constrained('emprendedores')->onDelete('cascade');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->integer('duracion_minutos');
            $table->integer('cantidad')->default(1);
            $table->decimal('precio', 10, 2)->nullable();
            $table->enum('estado', ['en_carrito', 'pendiente', 'confirmado', 'cancelado', 'completado'])->default('pendiente');
            $table->text('notas_cliente')->nullable();
            $table->text('notas_emprendedor')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reserva_servicios');
        Schema::dropIfExists('reservas');
        Schema::dropIfExists('servicio_horarios');
        
        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud', 'ubicacion_referencia']);
        });
        
        // Recrear las tablas originales
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->date('fecha');
            $table->text('descripcion')->nullable();
            $table->string('redes_url')->nullable();
            $table->string('tipo');
            $table->timestamps();
        });
        
        Schema::create('reserva_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained()->onDelete('cascade');
            $table->foreignId('emprendedor_id')->constrained('emprendedores')->onDelete('cascade');
            $table->string('descripcion')->nullable();
            $table->integer('cantidad')->nullable();
            $table->timestamps();
        });
    }
};