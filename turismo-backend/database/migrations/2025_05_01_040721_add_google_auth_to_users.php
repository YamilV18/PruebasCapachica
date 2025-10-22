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
        Schema::table('users', function (Blueprint $table) {
            // Campos para autenticación con Google
            $table->string('google_id')->nullable();
            $table->string('avatar')->nullable();
            
            // Añadir columna para foto de perfil si no existe
            if (!Schema::hasColumn('users', 'foto_perfil')) {
                $table->string('foto_perfil')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar']);
            
            // Solo eliminar foto_perfil si se creó en esta migración
            if (Schema::hasColumn('users', 'foto_perfil')) {
                $table->dropColumn('foto_perfil');
            }
        });
    }
};