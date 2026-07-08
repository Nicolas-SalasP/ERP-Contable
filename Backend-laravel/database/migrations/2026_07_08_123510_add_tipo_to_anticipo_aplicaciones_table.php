<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * anticipo_aplicaciones es una tabla compartida entre anticipos de proveedor y
 * de cliente (dos tablas distintas, anticipos_proveedores / anticipos_clientes,
 * con rangos de ID que pueden coincidir). Sin distinguir el tipo, revertir
 * aplicaciones de una factura de venta podia tocar por error un anticipo de
 * proveedor con el mismo ID (ver hallazgo de auditoria #11).
 *
 * Se agrega 'tipo' para filtrar siempre por el lado correcto, y se elimina la
 * foreign key de anticipo_id -> anticipos_proveedores porque esa columna ahora
 * es polimorfica (referencia anticipos_proveedores o anticipos_clientes segun
 * 'tipo'); la integridad referencial queda a cargo de los services.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anticipo_aplicaciones', function (Blueprint $table) {
            $table->dropForeign(['anticipo_id']);
        });

        Schema::table('anticipo_aplicaciones', function (Blueprint $table) {
            $table->string('tipo', 20)->default('proveedor')->after('anticipo_id');
            $table->index(['empresa_id', 'anticipo_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::table('anticipo_aplicaciones', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'anticipo_id', 'tipo']);
            $table->dropColumn('tipo');
        });

        Schema::table('anticipo_aplicaciones', function (Blueprint $table) {
            $table->foreign('anticipo_id')->references('id')->on('anticipos_proveedores')->onDelete('cascade');
        });
    }
};
