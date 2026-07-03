<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\CentroCosto;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Services\ContadorEmpresaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Domains\Contabilidad\Exceptions\ContabilidadException;

class AsientoContableService
{
    protected ContadorEmpresaService $contadorService;

    public function __construct(ContadorEmpresaService $contadorService)
    {
        $this->contadorService = $contadorService;
    }

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
        // El bloqueo de periodo es ahora una accion MANUAL y explicita gestionada por
        // PeriodoContableService (tabla periodos_contables). El observer de
        // AsientoContable aplica la misma garantia a nivel de modelo; este guard a
        // nivel de servicio se conserva para un mensaje temprano y claro.
        app(\App\Domains\Contabilidad\Services\PeriodoContableService::class)
            ->assertAbierto($empresaId, $fecha);
    }

    private function validarCentrosCosto(int $empresaId, array $detalles): void
    {
        $centroCostoIds = collect($detalles)
            ->pluck('centro_costo_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($centroCostoIds)) {
            return;
        }

        $centrosValidos = CentroCosto::whereIn('id', $centroCostoIds)
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->pluck('id')
            ->all();

        $centrosInvalidos = array_diff($centroCostoIds, $centrosValidos);

        if (!empty($centrosInvalidos)) {
            throw ContabilidadException::regla(
                "Centro(s) de costo invalido(s): " . implode(', ', $centrosInvalidos) .
                ". Deben existir en la empresa y estar activos."
            );
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
            throw ContabilidadException::regla("Rechazado por Partida Doble: El Debe ({$totalDebe}) no cuadra con el Haber ({$totalHaber}).");
        }

        $cuentasValidas = PlanCuenta::where('empresa_id', $datosAsiento['empresa_id'])
            ->whereIn('codigo', array_unique($codigosCuentas))
            ->get()
            ->keyBy('codigo');

        foreach ($codigosCuentas as $codigo) {
            if (!$cuentasValidas->has($codigo)) {
                throw ContabilidadException::regla("La cuenta contable {$codigo} no existe o pertenece a otra empresa.");
            }

            $cuenta = $cuentasValidas->get($codigo);

            if (!$cuenta->imputable) {
                throw ContabilidadException::regla("La cuenta contable {$codigo} no es imputable (es una cuenta padre o agrupadora).");
            }

            if (!$cuenta->activo) {
                throw ContabilidadException::regla("La cuenta contable {$codigo} se encuentra inactiva.");
            }
        }

        $this->validarCentrosCosto($datosAsiento['empresa_id'], $detalles);

        return DB::transaction(function () use ($datosAsiento, $detalles) {
            if (empty($datosAsiento['numero_comprobante'])) {
                $datosAsiento['numero_comprobante'] = 'TMP-' . Str::uuid()->toString();
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

    /**
     * Crea un asiento manual delegando en registrarAsiento para garantizar
     * partida doble, existencia/pertenencia/imputabilidad de cuentas y
     * validación de mes abierto (mismas reglas que cualquier asiento del sistema).
     */
    public function crearAsientoManual(array $datos): AsientoContable
    {
        $cabecera = [
            'empresa_id'    => $datos['empresa_id'],
            'usuario_id'    => $datos['usuario_id'] ?? null,
            'fecha'         => $datos['fecha'],
            'glosa'         => $datos['glosa'],
            'tipo_asiento'  => $datos['tipo'] ?? $datos['tipo_asiento'] ?? 'traspaso',
            'estado'        => $datos['estado'] ?? 'MAYORIZADO',
            'origen_modulo' => $datos['origen_modulo'] ?? 'tesoreria',
            'origen_id'     => $datos['origen_id'] ?? null,
        ];

        return $this->registrarAsiento($cabecera, $datos['detalles']);
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
        $correlativo = $this->contadorService->siguienteNumero(
            $asiento->empresa_id,
            'asiento_comprobante'
        );

        $secuencia = str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT);

        $asiento->update([
            'numero_comprobante' => $anio . $tipoCode . $secuencia
        ]);
    }

    public function reversarAsientoPorId(int $empresaId, int $userId, int $asientoId, string $fechaReversa, string $motivo)
    {
        $asientoOriginal = AsientoContable::where('empresa_id', $empresaId)->findOrFail($asientoId);

        if ($fechaReversa < $asientoOriginal->fecha->format('Y-m-d')) {
            throw ContabilidadException::regla('No puedes reversar con una fecha anterior al asiento original.');
        }

        return $this->procesarReversa($asientoOriginal, $userId, $fechaReversa, $motivo);
    }

    public function reversarAsiento(int $empresaId, int $userId, string $numeroComprobante, string $motivo, ?string $fechaReversa = null)
    {
        $asientoOriginal = AsientoContable::where('empresa_id', $empresaId)
            ->where('numero_comprobante', $numeroComprobante)
            ->firstOrFail();

        $fechaReversa = $fechaReversa ?? now()->toDateString();

        if ($fechaReversa < $asientoOriginal->fecha->format('Y-m-d')) {
            throw ContabilidadException::regla('No puedes reversar con una fecha anterior al asiento original.');
        }

        return $this->procesarReversa($asientoOriginal, $userId, $fechaReversa, $motivo);
    }

    private function procesarReversa(AsientoContable $asientoOriginal, int $userId, string $fechaReversa, string $motivo)
    {
        $asientoOriginal->load('detalles');
        $this->validarMesAbierto($asientoOriginal->empresa_id, $fechaReversa);

        if (in_array($asientoOriginal->estado, ['ANULADO', 'RECLASIFICADO'], true)) {
            throw ContabilidadException::regla('Este asiento ya se encontraba anulado o procesado internamente.');
        }

        return DB::transaction(function () use ($asientoOriginal, $userId, $fechaReversa, $motivo) {
            $asientoOriginal->update(['estado' => 'ANULADO']);

            $tempNum = 'TMP-' . Str::uuid()->toString();
            $nuevoAsiento = AsientoContable::create([
                'empresa_id' => $asientoOriginal->empresa_id,
                'fecha' => $fechaReversa,
                'glosa' => $motivo,
                'tipo_asiento' => 'traspaso',
                'origen_modulo' => $asientoOriginal->origen_modulo,
                'origen_id' => $asientoOriginal->origen_id,
                'usuario_id' => $userId,
                'estado' => 'MAYORIZADO',
                'numero_comprobante' => $tempNum
            ]);

            foreach ($asientoOriginal->detalles as $detalle) {
                /** @var \App\Domains\Contabilidad\Models\DetalleAsiento $detalle */
                $nuevoAsiento->detalles()->create([
                    'cuenta_contable' => $detalle->cuenta_contable,
                    'fecha' => $fechaReversa,
                    'tipo_operacion' => $detalle->debe > 0 ? 'HABER' : 'DEBE',
                    'debe' => $detalle->haber,
                    'haber' => $detalle->debe,
                    'descripcion_extensa' => "REVERSA: " . ($detalle->descripcion_extensa ?? 'Anulación'),
                    'centro_costo_id' => $detalle->centro_costo_id,
                    'empleado_nombre' => $detalle->empleado_nombre,
                ]);
            }

            $this->generarNumeroComprobante($nuevoAsiento);

            // Si el asiento reversado era el pago de una o más facturas, libera su
            // estado para que puedan volver a pagarse (mismo fix que AnulacionService).
            Factura::where('empresa_id', $asientoOriginal->empresa_id)
                ->where('asiento_pago_id', $asientoOriginal->id)
                ->update(['estado' => 'REGISTRADA', 'asiento_pago_id' => null]);

            return $nuevoAsiento;
        });
    }
}