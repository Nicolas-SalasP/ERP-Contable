<?php

namespace App\Domains\Rrhh\Services;

use App\Domains\Core\Models\Empresa;
use App\Domains\Rrhh\Models\ConceptoRemuneracion;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\LiquidacionDetalle;
use Illuminate\Support\Collection;

/** Genera los datos del Libro de Remuneraciones Digital (DFL-1 Art. 62 Código del Trabajo, obligatorio para empresas con 5+ trabajadores; formato digital requerido desde 2022 por Resolución Exenta N° 738 DT); incluye liquidaciones EMITIDA o PAGADA del período solicitado. */
class LibroRemuneracionesService
{
    /**
     * Genera los datos del libro de remuneraciones para el período.
     *
     * @return array{
     *   empresa: array{rut: string, razon_social: string},
     *   periodo: string,
     *   filas: list<array{
     *     rut: string,
     *     nombre: string,
     *     cargo: string,
     *     dias_trabajados: int,
     *     sueldo_base: int,
     *     horas_extras: int,
     *     total_haberes: int,
     *     descuento_previsional: int,
     *     descuento_legal: int,
     *     otros_descuentos: int,
     *     total_descuentos: int,
     *     liquido: int,
     *   }>,
     *   totales: array{
     *     sueldo_base: int,
     *     horas_extras: int,
     *     total_haberes: int,
     *     descuento_previsional: int,
     *     descuento_legal: int,
     *     otros_descuentos: int,
     *     total_descuentos: int,
     *     liquido: int,
     *   },
     *   cantidad_trabajadores: int,
     * }
     */
    public function generar(int $empresaId, int $anio, int $mes): array
    {
        $empresa = Empresa::find($empresaId);

        // Liquidaciones emitidas o pagadas del período, con empresa/empleado/contrato
        $liquidaciones = Liquidacion::where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->whereIn('estado', [Liquidacion::ESTADO_EMITIDA, Liquidacion::ESTADO_PAGADA])
            ->with(['empleado', 'contrato'])
            ->orderBy('id')
            ->get();

        if ($liquidaciones->isEmpty()) {
            return [
                'empresa'               => $this->datosEmpresa($empresa),
                'periodo'               => sprintf('%02d/%d', $mes, $anio),
                'filas'                 => [],
                'totales'               => $this->totalesVacios(),
                'cantidad_trabajadores' => 0,
            ];
        }

        // Carga masiva de detalles para evitar N+1
        $liquidacionIds = $liquidaciones->pluck('id')->all();
        $detallesPorLiq = LiquidacionDetalle::whereIn('liquidacion_id', $liquidacionIds)
            ->where('empresa_id', $empresaId)
            ->select('liquidacion_id', 'codigo_concepto', 'monto')
            ->get()
            ->groupBy('liquidacion_id');

        $filas = [];
        foreach ($liquidaciones as $liq) {
            $detalles = $detallesPorLiq->get($liq->id, collect());
            $filas[]  = $this->construirFila($liq, $detalles);
        }

        $totales = $this->calcularTotales($filas);

        return [
            'empresa'               => $this->datosEmpresa($empresa),
            'periodo'               => sprintf('%02d/%d', $mes, $anio),
            'filas'                 => $filas,
            'totales'               => $totales,
            'cantidad_trabajadores' => count($filas),
        ];
    }

    /**
     * Construye una fila del libro a partir de una liquidación.
     *
     * @param  Collection<int, LiquidacionDetalle>  $detalles
     * @return array{
     *   rut: string,
     *   nombre: string,
     *   cargo: string,
     *   dias_trabajados: int,
     *   sueldo_base: int,
     *   horas_extras: int,
     *   total_haberes: int,
     *   descuento_previsional: int,
     *   descuento_legal: int,
     *   otros_descuentos: int,
     *   total_descuentos: int,
     *   liquido: int,
     * }
     */
    private function construirFila(Liquidacion $liq, Collection $detalles): array
    {
        /** @var \App\Domains\Rrhh\Models\Empleado $empleado */
        $empleado = $liq->empleado;
        /** @var \App\Domains\Rrhh\Models\Contrato|null $contrato */
        $contrato = $liq->contrato;

        $nombre = implode(' ', array_filter([
            $empleado->nombres,
            $empleado->apellido_paterno,
            $empleado->apellido_materno,
        ]));

        $rut   = $empleado->rut ?? '—';
        $cargo = $contrato !== null ? ($contrato->cargo ?? '—') : '—';

        $sueldoBase   = $this->montoDetalle($detalles, ConceptoRemuneracion::SUELDO_BASE);
        $horasExtras  = $this->montoDetalle($detalles, ConceptoRemuneracion::HORAS_EXTRA);

        // Descuentos previsionales: AFP (cotización + comisión) + salud + AFC trabajador
        $afpCot      = $this->montoDetalle($detalles, ConceptoRemuneracion::AFP_COTIZACION);
        $afpComision = $this->montoDetalle($detalles, ConceptoRemuneracion::AFP_COMISION);
        $salud       = $this->montoDetalle($detalles, ConceptoRemuneracion::SALUD);
        $afcTrab     = $this->montoDetalle($detalles, ConceptoRemuneracion::AFC_TRABAJADOR);
        $descPrev    = $afpCot + $afpComision + $salud + $afcTrab;

        // Descuento legal: impuesto único de segunda categoría
        $descLegal = $this->montoDetalle($detalles, ConceptoRemuneracion::IMPUESTO_UNICO);

        // Otros descuentos: descuentos voluntarios del header
        $otrosDesc = (int) round((float) ($liq->total_descuentos_voluntarios ?? 0));

        $totalDesc  = $descPrev + $descLegal + $otrosDesc;
        $totalHab   = (int) round((float) ($liq->total_haberes ?? 0));
        $liquido    = (int) round((float) ($liq->liquido_a_pagar ?? 0));
        $diasTrab   = (int) ($liq->dias_trabajados ?? 0);

        return [
            'rut'                   => $rut,
            'nombre'                => $nombre,
            'cargo'                 => $cargo,
            'dias_trabajados'       => $diasTrab,
            'sueldo_base'           => $sueldoBase,
            'horas_extras'          => $horasExtras,
            'total_haberes'         => $totalHab,
            'descuento_previsional' => $descPrev,
            'descuento_legal'       => $descLegal,
            'otros_descuentos'      => $otrosDesc,
            'total_descuentos'      => $totalDesc,
            'liquido'               => $liquido,
        ];
    }

    /**
     * Calcula los totales de las columnas numéricas.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return array{
     *   sueldo_base: int,
     *   horas_extras: int,
     *   total_haberes: int,
     *   descuento_previsional: int,
     *   descuento_legal: int,
     *   otros_descuentos: int,
     *   total_descuentos: int,
     *   liquido: int,
     * }
     */
    private function calcularTotales(array $filas): array
    {
        $totales = $this->totalesVacios();

        foreach ($filas as $fila) {
            $totales['sueldo_base']           += (int) ($fila['sueldo_base'] ?? 0);
            $totales['horas_extras']           += (int) ($fila['horas_extras'] ?? 0);
            $totales['total_haberes']          += (int) ($fila['total_haberes'] ?? 0);
            $totales['descuento_previsional']  += (int) ($fila['descuento_previsional'] ?? 0);
            $totales['descuento_legal']        += (int) ($fila['descuento_legal'] ?? 0);
            $totales['otros_descuentos']       += (int) ($fila['otros_descuentos'] ?? 0);
            $totales['total_descuentos']       += (int) ($fila['total_descuentos'] ?? 0);
            $totales['liquido']                += (int) ($fila['liquido'] ?? 0);
        }

        return $totales;
    }

    /**
     * @return array{
     *   sueldo_base: int,
     *   horas_extras: int,
     *   total_haberes: int,
     *   descuento_previsional: int,
     *   descuento_legal: int,
     *   otros_descuentos: int,
     *   total_descuentos: int,
     *   liquido: int,
     * }
     */
    private function totalesVacios(): array
    {
        return [
            'sueldo_base'           => 0,
            'horas_extras'          => 0,
            'total_haberes'         => 0,
            'descuento_previsional' => 0,
            'descuento_legal'       => 0,
            'otros_descuentos'      => 0,
            'total_descuentos'      => 0,
            'liquido'               => 0,
        ];
    }

    /**
     * @return array{rut: string, razon_social: string}
     */
    private function datosEmpresa(?Empresa $empresa): array
    {
        if ($empresa === null) {
            return ['rut' => '', 'razon_social' => ''];
        }

        return [
            'rut'          => $empresa->rut ?? '',
            'razon_social' => $empresa->razon_social ?? '',
        ];
    }

    /**
     * Suma los montos de un código de concepto en los detalles de la liquidación.
     *
     * @param  Collection<int, LiquidacionDetalle>  $detalles
     */
    private function montoDetalle(Collection $detalles, string $codigo): int
    {
        $item = $detalles->first(fn ($d) => $d->codigo_concepto === $codigo);
        return $item !== null ? (int) round((float) ($item->monto ?? 0)) : 0;
    }
}
