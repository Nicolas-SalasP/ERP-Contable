<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'numero_cotizacion')) {
                $table->string('numero_cotizacion')->nullable()->after('nombre_cliente');
                $table->date('fecha_validez')->nullable()->after('fecha_emision');
                $table->decimal('subtotal', 12, 2)->default(0)->after('validez');
                $table->decimal('porcentaje_descuento', 5, 2)->default(0)->after('subtotal');
                $table->decimal('monto_descuento', 12, 2)->default(0)->after('porcentaje_descuento');
                $table->decimal('monto_neto', 12, 2)->default(0)->after('monto_descuento');
                $table->decimal('porcentaje_iva', 5, 2)->default(19)->after('monto_neto');
                $table->decimal('monto_iva', 12, 2)->default(0)->after('porcentaje_iva');
                $table->decimal('monto_total', 12, 2)->default(0)->after('monto_iva');
                $table->text('notas_condiciones')->nullable()->after('es_afecta');
            }
        });

        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizacion_detalles', 'precio_unitario')) {
                $table->decimal('precio_unitario', 12, 2)->default(0)->after('cantidad');
                $table->decimal('subtotal', 12, 2)->default(0)->after('precio_unitario');
            }
        });
    }

    public function down()
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn([
                'numero_cotizacion', 'fecha_validez', 'subtotal', 
                'porcentaje_descuento', 'monto_descuento', 'monto_neto', 
                'porcentaje_iva', 'monto_iva', 'monto_total', 'notas_condiciones'
            ]);
        });

        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            $table->dropColumn(['precio_unitario', 'subtotal']);
        });
    }
};