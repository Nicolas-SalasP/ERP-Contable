<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Domains\Core\Models\Empresa;

class FacturasYAsientosSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();
        if (!$empresa) {
            return;
        }

        $now = Carbon::now();

        $provId = DB::table('proveedores')->where('codigo_interno', 'PROV-001')->value('id');
        if (!$provId) {
            $provId = DB::table('proveedores')->insertGetId([
                'empresa_id'      => $empresa->id,
                'codigo_interno'  => 'PROV-001',
                'rut'             => '11.111.111-1',
                'razon_social'    => 'Distribuidora de Insumos SpA',
                'pais_iso'        => 'CL',
                'moneda_defecto'  => 'CLP',
                'created_at'      => $now,
            ]);
        }

        $proyectoId = DB::table('proyectos_activos')
            ->where('nombre', 'Instalación Servidor Principal')
            ->value('id_proyecto');

        if (!$proyectoId) {
            $proyectoId = DB::table('proyectos_activos')->insertGetId([
                'empresa_id'            => $empresa->id,
                'nombre'                => 'Instalación Servidor Principal',
                'vida_util_meses'       => 60,
                'valor_total_original'  => 0,
                'estado'                => 'EN_CONSTRUCCION',
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);
        }

        $this->crearFacturaCompleta($empresa->id, $provId, 'F-500',  100000, $now->copy()->subDays(2),  'REGISTRADA', null,       'TR-5001', '2026005001');
        $this->crearFacturaCompleta($empresa->id, $provId, 'F-1020', 500000, $now->copy()->subDays(10), 'PAGADA',     null,       'TR-5002', '2026005002');
        $this->crearFacturaCompleta($empresa->id, $provId, 'F-8821', 250000, $now->copy()->subDays(5),  'REGISTRADA', $proyectoId, 'TR-5003', '2026005003');
    }

    private function crearFacturaCompleta(
        int $empresaId,
        int $provId,
        string $numero,
        int $neto,
        Carbon $fecha,
        string $estado,
        ?int $proyectoId,
        string $numComprobante,
        string $codigoUnico,
    ): void {
        $yaExiste = DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->where('numero_factura', $numero)
            ->exists();

        if ($yaExiste) {
            return;
        }

        $iva   = $neto * 0.19;
        $bruto = $neto + $iva;

        $asientoId = DB::table('asientos_contables')->insertGetId([
            'empresa_id'         => $empresaId,
            'codigo_unico'       => $codigoUnico,
            'numero_comprobante' => $numComprobante,
            'fecha'              => $fecha->format('Y-m-d'),
            'glosa'              => "Centralización Factura de Compra N° {$numero}",
            'tipo_asiento'       => 'traspaso',
            'origen_modulo'      => 'compras',
            'estado'             => 'CONTABILIZADO',
            'created_at'         => Carbon::now(),
        ]);

        DB::table('detalles_asiento')->insert([
            [
                'asiento_id'          => $asientoId,
                'cuenta_contable'     => '606730',
                'debe'                => $neto,
                'haber'               => 0,
                'descripcion_extensa' => "Gasto neto factura {$numero}",
            ],
            [
                'asiento_id'          => $asientoId,
                'cuenta_contable'     => '152540',
                'debe'                => $iva,
                'haber'               => 0,
                'descripcion_extensa' => "IVA Crédito Fiscal {$numero}",
            ],
            [
                'asiento_id'          => $asientoId,
                'cuenta_contable'     => '352105',
                'debe'                => 0,
                'haber'               => $bruto,
                'descripcion_extensa' => "Obligación proveedor {$numero}",
            ],
        ]);

        $facturaId = DB::table('facturas')->insertGetId([
            'empresa_id'           => $empresaId,
            'codigo_unico'         => crc32($empresaId . $numero),
            'proveedor_id'         => $provId,
            'proyecto_activo_id'   => $proyectoId,
            'numero_factura'       => $numero,
            'tipo'                 => 'COMPRA',
            'fecha_emision'        => $fecha->format('Y-m-d'),
            'monto_neto'           => $neto,
            'monto_iva'            => $iva,
            'monto_bruto'          => $bruto,
            'estado'               => $estado,
            'comprobante_contable' => $codigoUnico,
            'created_at'           => $fecha,
        ]);

        DB::table('auditorias')->insert([
            'auditable_type'    => 'App\Domains\Comercial\Models\Factura',
            'auditable_id'      => $facturaId,
            'nombre_usuario'    => 'Dueño Super Admin',
            'operacion'         => 'CREACIÓN',
            'estado_anterior'   => null,
            'estado_nuevo'      => 'REGISTRADA',
            'detalle'           => 'Ingreso de documento y generación de asiento contable automático.',
            'referencia_cruzada' => $codigoUnico,
            'created_at'        => $fecha,
        ]);

        if ($estado === 'PAGADA') {
            DB::table('auditorias')->insert([
                'auditable_type'    => 'App\Domains\Comercial\Models\Factura',
                'auditable_id'      => $facturaId,
                'nombre_usuario'    => 'Experto Contador',
                'operacion'         => 'PAGO_CONCILIADO',
                'estado_anterior'   => 'REGISTRADA',
                'estado_nuevo'      => 'PAGADA',
                'detalle'           => 'Documento pagado y conciliado con el banco.',
                'referencia_cruzada' => 'EG-' . substr($codigoUnico, -4),
                'created_at'        => $fecha->copy()->addDays(2),
            ]);
        }

        if ($proyectoId) {
            DB::table('auditorias')->insert([
                'auditable_type'    => 'App\Domains\Comercial\Models\Factura',
                'auditable_id'      => $facturaId,
                'nombre_usuario'    => 'Administrador',
                'operacion'         => 'ASIGNACIÓN_ACTIVO',
                'estado_anterior'   => 'REGISTRADA',
                'estado_nuevo'      => 'REGISTRADA',
                'detalle'           => 'Factura imputada al costo de un proyecto de Activo Fijo.',
                'referencia_cruzada' => 'PRJ-' . $proyectoId,
                'created_at'        => $fecha->copy()->addHours(5),
            ]);
        }
    }
}
