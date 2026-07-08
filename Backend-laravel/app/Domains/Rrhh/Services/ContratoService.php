<?php

namespace App\Domains\Rrhh\Services;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\HaberDescuentoContrato;
use Illuminate\Support\Facades\DB;

class ContratoService
{
    public function listarPorEmpleado(int $empresaId, int $empleadoId)
    {
        return Contrato::where('empresa_id', $empresaId)
            ->where('empleado_id', $empleadoId)
            ->with(['centroCosto', 'haberes'])
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    public function obtener(int $empresaId, int $id): Contrato
    {
        $contrato = Contrato::where('empresa_id', $empresaId)
            ->with(['empleado', 'centroCosto', 'haberes.concepto'])
            ->find($id);

        if (!$contrato) {
            throw RrhhException::noEncontrado('El contrato no existe o no pertenece a la empresa.');
        }

        return $contrato;
    }

    public function crear(int $empresaId, int $empleadoId, array $datos, array $haberes = []): Contrato
    {
        return DB::transaction(function () use ($empresaId, $empleadoId, $datos, $haberes) {
            $empleado = Empleado::where('empresa_id', $empresaId)->find($empleadoId);
            if (!$empleado) {
                throw RrhhException::noEncontrado('El empleado no existe o no pertenece a la empresa.');
            }

            // Desactivar contrato anterior si existe
            Contrato::where('empresa_id', $empresaId)
                ->where('empleado_id', $empleadoId)
                ->where('es_contrato_activo', true)
                ->update(['es_contrato_activo' => false]);

            $contrato = Contrato::create(array_merge($datos, [
                'empresa_id' => $empresaId,
                'empleado_id' => $empleadoId,
                'estado' => 'VIGENTE',
                'es_contrato_activo' => true,
            ]));

            // Recontratación: si el empleado quedó INACTIVO por un finiquito/término anterior,
            // el nuevo contrato lo reactiva (afecta filtro de listado y derechos ARCO en ArcoService).
            if ($empleado->estado === 'INACTIVO') {
                $empleado->update(['estado' => 'ACTIVO']);
            }

            foreach ($haberes as $haber) {
                HaberDescuentoContrato::create(array_merge($haber, [
                    'empresa_id' => $empresaId,
                    'contrato_id' => $contrato->id,
                ]));
            }

            return $contrato->load(['empleado', 'haberes']);
        });
    }

    public function terminar(int $empresaId, int $id, array $datos): Contrato
    {
        return DB::transaction(function () use ($empresaId, $id, $datos) {
            $contrato = $this->obtener($empresaId, $id);

            if ($contrato->estado === 'TERMINADO') {
                throw RrhhException::regla('El contrato ya está terminado.');
            }

            $contrato->update([
                'estado' => 'TERMINADO',
                'es_contrato_activo' => false,
                'causal_termino' => $datos['causal_termino'],
                'fecha_termino_real' => $datos['fecha_termino_real'],
                'observaciones_termino' => $datos['observaciones_termino'] ?? null,
            ]);

            $contrato->empleado->update(['estado' => 'INACTIVO']);

            return $contrato->fresh();
        });
    }

    public function agregarHaber(int $empresaId, int $contratoId, array $datos): HaberDescuentoContrato
    {
        $contrato = $this->obtener($empresaId, $contratoId);

        if ($contrato->estado !== 'VIGENTE') {
            throw RrhhException::regla('Solo se pueden agregar haberes a contratos vigentes.');
        }

        return HaberDescuentoContrato::create(array_merge($datos, [
            'empresa_id' => $empresaId,
            'contrato_id' => $contratoId,
        ]));
    }
}
