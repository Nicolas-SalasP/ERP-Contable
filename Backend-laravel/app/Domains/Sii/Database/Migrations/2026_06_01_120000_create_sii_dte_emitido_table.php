<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecera inmutable de cada DTE emitido. Snapshot de Empresa / Cliente /
 * totales al momento de la emision: cambios posteriores en Comercial NO
 * deben alterar un DTE ya generado (exigencia legal SII, retencion 6 anos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_dte_emitido', function (Blueprint $table) {
            $table->id();

            // -------- IDENTIFICACION --------
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('factura_id')->nullable()->constrained('facturas')->nullOnDelete();
            $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones')->nullOnDelete();
            $table->string('origen_externo', 100)->nullable();
            $table->unsignedSmallInteger('tipo_dte');
            $table->unsignedInteger('folio');
            $table->date('fecha_emision');
            $table->unsignedBigInteger('caf_id')->nullable(); // FK logica a sii_caf (F3)

            // -------- SNAPSHOT EMISOR --------
            $table->string('emisor_rut', 10);
            $table->string('emisor_razon_social', 100);
            $table->string('emisor_giro', 80)->nullable();
            $table->unsignedInteger('emisor_acteco')->nullable();
            $table->string('emisor_direccion', 70)->nullable();
            $table->string('emisor_comuna', 20)->nullable();
            $table->string('emisor_ciudad', 20)->nullable();
            $table->string('emisor_cdg_sii_sucursal', 9)->nullable();

            // -------- SNAPSHOT RECEPTOR --------
            $table->string('receptor_rut', 10);
            $table->string('receptor_razon_social', 100);
            $table->string('receptor_giro', 80)->nullable();
            $table->string('receptor_direccion', 70)->nullable();
            $table->string('receptor_comuna', 20)->nullable();
            $table->string('receptor_ciudad', 20)->nullable();
            $table->string('receptor_contacto', 80)->nullable();
            $table->string('receptor_correo', 80)->nullable();

            // -------- TOTALES --------
            $table->string('moneda', 3)->default('CLP');
            $table->decimal('monto_neto', 18, 2)->default(0);
            $table->decimal('monto_exento', 18, 2)->default(0);
            $table->decimal('tasa_iva', 5, 2)->default(19.00);
            $table->decimal('iva', 18, 2)->default(0);
            $table->decimal('iva_no_retenido', 18, 2)->default(0);
            $table->decimal('monto_impuesto_adicional', 18, 2)->default(0);
            $table->decimal('descuento_global_monto', 18, 2)->default(0);
            $table->decimal('monto_total', 18, 2);

            // -------- FORMA DE PAGO / VENCIMIENTO --------
            $table->unsignedTinyInteger('forma_pago_codigo')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('condicion_pago', 100)->nullable();

            // -------- ESTADO Y TRACKING SII --------
            $table->string('estado', 40)->default('BORRADOR');
            $table->string('track_id', 40)->nullable();
            $table->string('codigo_respuesta_sii', 10)->nullable();
            $table->string('glosa_sii', 500)->nullable();
            $table->timestamp('fecha_envio_sii')->nullable();
            $table->timestamp('fecha_aceptacion_sii')->nullable();
            $table->timestamp('fecha_rechazo_sii')->nullable();

            // -------- ARCHIVOS GENERADOS --------
            $table->string('xml_path', 255)->nullable();
            $table->string('xml_hash_sha256', 64)->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->text('ted_xml')->nullable();

            // -------- INDICADORES SII --------
            $table->boolean('es_cedible')->default(true);
            $table->unsignedTinyInteger('indicador_servicio')->nullable();

            // -------- AUDITORIA --------
            // FK a `usuarios` (la migracion 2026_04_23_100003 renombra `users` -> `usuarios`).
            $table->foreignId('usuario_emisor_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $table->timestamps();

            // -------- INDICES / RESTRICCIONES --------
            $table->unique(['empresa_id', 'tipo_dte', 'folio'], 'sii_dte_emitido_empresa_tipo_folio_unique');
            $table->index(['empresa_id', 'estado'], 'sii_dte_emitido_empresa_estado_idx');
            $table->index('track_id', 'sii_dte_emitido_track_id_idx');
            $table->index('fecha_emision', 'sii_dte_emitido_fecha_emision_idx');
            // factura_id ya tiene index implicito por la FK; no se duplica.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_dte_emitido');
    }
};
