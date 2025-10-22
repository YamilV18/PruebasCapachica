<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmprendedoresTable extends Migration
{
    public function up()
    {
        Schema::create('emprendedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo_servicio');
            $table->text('descripcion');
            $table->string('ubicacion');
            $table->string('telefono');
            $table->string('email');
            $table->string('pagina_web')->nullable();
            $table->string('horario_atencion');
            $table->string('precio_rango');
            $table->json('metodos_pago')->nullable();
            $table->integer('capacidad_aforo')->nullable();
            $table->integer('numero_personas_atiende')->nullable();
            $table->text('comentarios_resenas')->nullable();
            $table->json('imagenes')->nullable();
            $table->string('categoria');
            $table->string('certificaciones')->nullable();
            $table->string('idiomas_hablados')->nullable();
            $table->string('opciones_acceso')->nullable();
            $table->boolean('facilidades_discapacidad')->default(false);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('emprendedores');
    }
}
