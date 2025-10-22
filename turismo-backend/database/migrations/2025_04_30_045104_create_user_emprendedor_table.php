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
        Schema::create('user_emprendedor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('emprendedor_id')->constrained('emprendedores')->onDelete('cascade');
            $table->boolean('es_principal')->default(false); // Para identificar al dueño principal
            $table->string('rol')->default('administrador'); // Posibles roles: administrador, colaborador, etc.
            $table->timestamps();
            
            // Índices
            $table->unique(['user_id', 'emprendedor_id']);
        });
        
        // Agregar campo de foto de perfil en usuarios
        Schema::table('users', function (Blueprint $table) {
            $table->string('foto_perfil')->nullable()->after('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('foto_perfil');
        });
        
        Schema::dropIfExists('user_emprendedor');
    }
};