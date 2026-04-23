<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cuentas_bancarias_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->string('banco', 100);
            $table->string('tipo_cuenta', 50);
            $table->string('numero_cuenta', 50);
            $table->string('cuenta_contable', 20)->nullable();
            $table->decimal('saldo_actual', 15, 2)->default(0.00);
            $table->string('titular', 150);
            $table->string('rut_titular', 20);
            $table->string('email_notificacion', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias_empresa');
    }
};
