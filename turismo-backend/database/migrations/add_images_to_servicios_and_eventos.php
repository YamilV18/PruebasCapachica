<?php

// database/migrations/2025_10_20_000001_add_images_to_servicios_and_eventos.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('servicios', function (Blueprint $t) {
            $t->string('imagen')->nullable()->after('ubicacion_referencia');
            $t->json('galeria')->nullable()->after('imagen');
        });
        Schema::table('eventos', function (Blueprint $t) {
            $t->string('imagen')->nullable()->after('que_llevar');
            $t->json('galeria')->nullable()->after('imagen');
        });
    }
    public function down(): void {
        Schema::table('servicios', function (Blueprint $t) {
            $t->dropColumn(['imagen','galeria']);
        });
        Schema::table('eventos', function (Blueprint $t) {
            $t->dropColumn(['imagen','galeria']);
        });
    }
};
