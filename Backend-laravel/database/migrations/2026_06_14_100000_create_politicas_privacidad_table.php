<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('politicas_privacidad', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('version')->unique();
            $table->string('titulo');
            $table->text('contenido');
            $table->date('vigente_desde');
            $table->boolean('activa')->default(false);
            $table->timestamps();
        });

        DB::table('politicas_privacidad')->insert([
            'version'       => '1.0',
            'titulo'        => 'Política de Privacidad — Tenri ERP',
            'contenido'     => 'Tenri ERP trata sus datos personales conforme a la Ley N°21.719 (Chile). Los datos recopilados se usan exclusivamente para la gestión contable, laboral y tributaria de su empresa. Usted tiene derecho a acceder, rectificar, cancelar y oponerse al tratamiento de sus datos. Contáctenos a privacidad@tenri.cl para ejercer sus derechos.',
            'vigente_desde' => now()->toDateString(),
            'activa'        => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('politicas_privacidad');
    }
};
