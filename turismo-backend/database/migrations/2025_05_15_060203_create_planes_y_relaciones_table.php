<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Migración para crear la tabla de planes
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->integer('capacidad')->default(1);
            $table->boolean('es_publico')->default(true);
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->foreignId('creado_por_usuario_id')->constrained('users');
            $table->timestamps();
        });

        // Tabla pivote para los servicios del plan
        Schema::create('plan_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained('servicios')->onDelete('cascade');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->integer('duracion_minutos');
            $table->text('notas')->nullable();
            $table->integer('orden')->default(0);
            $table->timestamps();
        });

        // Tabla para las inscripciones de usuarios a planes
        Schema::create('plan_inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('estado', ['pendiente', 'confirmada', 'cancelada'])->default('pendiente');
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_inscripciones');
        Schema::dropIfExists('plan_servicios');
        Schema::dropIfExists('planes');
    }
};