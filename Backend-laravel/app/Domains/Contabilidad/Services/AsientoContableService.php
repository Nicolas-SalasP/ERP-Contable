<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\DetalleAsiento;
use App\Domains\Contabilidad\Models\PlanCuenta;
use Illuminate\Support\Facades\DB;
use Exception;

class AsientoContableService
{
    public function obtenerAsientosPaginados(int $empresaId)
    {
        return AsientoContable::where('empresa_id', $empresaId)
            ->with('detalles.cuenta')
            ->orderBy('fecha', 'desc')
            ->paginate(15);
    }

    public function obtenerAsientoPorId(int $empresaId, int $id)
    {
        return AsientoContable::where('empresa_id', $empresaId)
            ->with('detalles.cuenta')
            ->findOrFail($id);
    }

    private function validarMesAbierto(int $empresaId, string $fecha)
    {
        $mes = date('n', strtotime($fecha));
        $anio = date('Y', strtotime($fecha));

        $mesCerrado = AsientoContable::where('empresa_id', $empresaId)
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->where('glosa', 'like', '%Cierre F29%')
            ->exists();

        if ($mesCerrado) {
            throw new Exception("El periodo {$mes}/{$anio} ya está cerrado tributariamente.");
        }
    }

    public function registrarAsiento(array $datosAsiento, array $detalles): AsientoContable
    {
        $this->validarMesAbierto($datosAsiento['empresa_id'], $datosAsiento['fecha']);

        $totalDebe = 0;
        $totalHaber = 0;
        $codigosCuentas = [];

        foreach ($detalles as $detalle) {
            $totalDebe += round((float) ($detalle['debe'] ?? 0), 2);
            $totalHaber += round((float) ($detalle['haber'] ?? 0), 2);
            $codigosCuentas[] = $detalle['cuenta_contable'];
        }

        $totalDebe = round($totalDebe, 2);
        $totalHaber = round($totalHaber, 2);
        $diferencia = round(abs($totalDebe - $totalHaber), 2);

        if ($diferencia > 0.00) {
            throw new Exception("Rechazado por Partida Doble: El Debe ({$totalDebe}) no cuadra con el Haber ({$totalHaber}).");
        }

        $cuentasValidas = PlanCuenta::where('empresa_id', $datosAsiento['empresa_id'])
            ->whereIn('codigo', array_unique($codigosCuentas))
            ->get()
            ->keyBy('codigo');

        foreach ($codigosCuentas as $codigo) {
            if (!$cuentasValidas->has($codigo)) {
                throw new Exception("La cuenta contable {$codigo} no existe o pertenece a otra empresa.");
            }

            $cuenta = $cuentasValidas->get($codigo);

            if (!$cuenta->imputable) {
                throw new Exception("La cuenta contable {$codigo} no es imputable (es una cuenta padre o agrupadora).");
            }

            if (!$cuenta->activo) {
                throw new Exception("La cuenta contable {$codigo} se encuentra inactiva.");
            }
        }

        return DB::transaction(function () use ($datosAsiento, $detalles) {
            if (empty($datosAsiento['numero_comprobante'])) {
                $datosAsiento['numero_comprobante'] = 'T' . time() . rand(10, 99);
            }

            $asiento = AsientoContable::create($datosAsiento);

            foreach ($detalles as $detalle) {
                $asiento->detalles()->create([
                    'cuenta_contable' => $detalle['cuenta_contable'],
                    'fecha' => $detalle['fecha'] ?? $asiento->fecha,
                    'tipo_operacion' => $detalle['tipo_operacion'] ?? ($detalle['debe'] > 0 ? 'DEBE' : 'HABER'),
                    'debe' => $detalle['debe'] ?? 0.00,
                    'haber' => $detalle['haber'] ?? 0.00,
                    'descripcion_extensa' => $detalle['glosa_detalle'] ?? null,
                    'centro_costo_id' => $detalle['centro_costo_id'] ?? null,
                    'empleado_nombre' => $detalle['empleado_nombre'] ?? null,
                ]);
            }
            $this->generarNumeroComprobante($asiento);

            return $asiento;
        });
    }

    public function crearAsientoManual(array $datos)
    {
        $this->validarMesAbierto($datos['empresa_id'], $datos['fecha']);

        return DB::transaction(function () use ($datos) {
            $tempNum = 'T' . time() . rand(10, 99);

            $asiento = AsientoContable::create([
                'empresa_id' => $datos['empresa_id'],
                'usuario_id' => $datos['usuario_id'],
                'fecha' => $datos['fecha'],
                'glosa' => $datos['glosa'],
                'tipo_asiento' => $datos['tipo'] ?? 'traspaso',
                'estado' => 'MAYORIZADO',
                'numero_comprobante' => $tempNum,
                'origen_modulo' => $datos['origen_modulo'] ?? 'tesoreria',
                'origen_id' => $datos['origen_id'] ?? null,
            ]);

            $this->generarNumeroComprobante($asiento);

            foreach ($datos['detalles'] as $detalle) {
                DetalleAsiento::create([
                    'asiento_id' => $asiento->id,
                    'cuenta_contable' => $detalle['cuenta_contable'],
                    'debe' => $detalle['debe'] ?? 0,
                    'haber' => $detalle['haber'] ?? 0,
                    'fecha' => $datos['fecha'],
                    'tipo_operacion' => $detalle['tipo_operacion'] ?? ($detalle['debe'] > 0 ? 'DEBE' : 'HABER'),
                    'descripcion_extensa' => $detalle['glosa_detalle'] ?? null,
                    'centro_costo_id' => $detalle['centro_costo_id'] ?? null,
                    'empleado_nombre' => $detalle['empleado_nombre'] ?? null,
                ]);
            }

            return $asiento;
        });
    }

    public function existeAsientoPorOrigen(int $empresaId, string $modulo, int $origenId, string $fecha, string $glosa): bool
    {
        return AsientoContable::where('empresa_id', $empresaId)
            ->where('origen_modulo', $modulo)
            ->where('origen_id', $origenId)
            ->where('fecha', $fecha)
            ->where('glosa', $glosa)
            ->exists();
    }

    private function generarNumeroComprobante(AsientoContable $asiento): void
    {
        $anio = date('y', strtotime($asiento->fecha ?? date('Y-m-d')));
        $tipoCode = '10';
        $secuencia = str_pad($asiento->id, 6, '0', STR_PAD_LEFT);

        $asiento->update([
            'numero_comprobante' => $anio . $tipoCode . $secuencia
        ]);
    }
}