<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Red de seguridad contra comprobantes contables duplicados: indice unico
     * (empresa_id, numero_comprobante). El correlativo definitivo ya se genera
     * de forma atomica (ContadorEmpresaService con lockForUpdate); este indice
     * garantiza la unicidad a nivel de BD. La columna se reduce a varchar(50)
     * (los valores reales son cortos) para mantener el indice dentro de los
     * limites de longitud de clave de MySQL.
     */
    public function up(): void
    {
        Schema::table('asientos_contables', function (Blueprint $table) {
            $table->string('numero_comprobante', 50)->change();
        });

        // Idempotente: solo crea el indice si aun no existe.
        if (!Schema::hasIndex('asientos_contables', 'asientos_empresa_comprobante_unique')) {
            Schema::table('asientos_contables', function (Blueprint $table) {
                $table->unique(['empresa_id', 'numero_comprobante'], 'asientos_empresa_comprobante_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('asientos_contables', function (Blueprint $table) {
            $table->dropUnique('asientos_empresa_comprobante_unique');
            $table->string('numero_comprobante', 255)->change();
        });
    }
};
