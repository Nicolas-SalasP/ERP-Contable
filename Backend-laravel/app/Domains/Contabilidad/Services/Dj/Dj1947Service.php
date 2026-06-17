<?php

namespace App\Domains\Contabilidad\Services\Dj;

use App\Domains\Contabilidad\Contracts\DeclaracionJuradaContract;
use App\Domains\Contabilidad\DataTransfer\DjData;
use App\Domains\Contabilidad\DataTransfer\DjLineaData;
use App\Domains\Contabilidad\Exceptions\DjException;
use App\Domains\Contabilidad\Services\Propyme\ResultadoTributarioPropymeService;
use App\Domains\Core\Models\Empresa;

class Dj1947Service implements DeclaracionJuradaContract
{
    public function __construct(
        private readonly ResultadoTributarioPropymeService $resultadoService,
    ) {}

    public function codigo(): string
    {
        return '1947';
    }

    public function construir(int $empresaId, int $anio): DjData
    {
        $empresa = Empresa::findOrFail($empresaId);

        if (($empresa->regimen_tributario ?? '') !== '14_D8') {
            throw DjException::regla(
                'La DJ 1947 solo aplica a empresas en régimen Propyme Transparente (14 D N°8).'
            );
        }

        $resultado = $this->resultadoService->calcular($empresaId, $anio);

        if (empty($resultado['distribucion'])) {
            throw DjException::regla('No hay propietarios registrados para generar la DJ 1947.');
        }

        $lineas = [];
        foreach ($resultado['distribucion'] as $prop) {
            $lineas[] = new DjLineaData([
                'rut_propietario'    => $prop['rut'],
                'nombre_propietario' => $prop['nombre'],
                'porcentaje'         => $prop['porcentaje'],
                'base_atribuida'     => $prop['base_atribuida'],
                'creditos'           => $prop['creditos'],  // 0 para 14D N°8 (exento IDPC)
                'ppm_atribuido'      => $prop['ppm_atribuido'],
            ]);
        }

        return new DjData(
            codigoDj:  '1947',
            empresaId: $empresaId,
            anio:      $anio,
            cabecera: [
                'rut_empresa'      => $empresa->rut,
                'razon_social'     => $empresa->razon_social,
                'anio'             => $anio,
                'base_imponible'   => $resultado['base_imponible'],
                'ppm_total'        => $resultado['ppm_total'],
                'ingresos_totales' => $resultado['ingresos_totales'],
                'gastos_totales'   => $resultado['gastos_totales'],
            ],
            lineas: $lineas,
        );
    }

    public function validar(DjData $data): array
    {
        $errores = [];

        if (empty($data->cabecera['rut_empresa'])) {
            $errores[] = 'RUT de empresa vacío.';
        }

        if (empty($data->lineas)) {
            $errores[] = 'No hay propietarios declarados.';
        }

        $sumaPorcentajes = 0.0;
        $sumaBase        = 0;

        foreach ($data->lineas as $i => $linea) {
            $n = $i + 1;

            if (empty($linea->campos['rut_propietario'])) {
                $errores[] = "Propietario #{$n}: RUT vacío.";
            }

            $porcentaje = (float) ($linea->campos['porcentaje'] ?? 0);
            if ($porcentaje <= 0 || $porcentaje > 100) {
                $errores[] = "Propietario #{$n}: porcentaje de participación inválido ({$porcentaje}%).";
            }
            $sumaPorcentajes += $porcentaje;

            $ppm = (int) ($linea->campos['ppm_atribuido'] ?? 0);
            if ($ppm < 0) {
                $errores[] = "Propietario #{$n}: PPM a disposición no puede ser negativo.";
            }

            $sumaBase += (int) ($linea->campos['base_atribuida'] ?? 0);
        }

        if (! empty($data->lineas) && abs($sumaPorcentajes - 100.0) > 0.01) {
            $errores[] = "Los porcentajes de participación suman {$sumaPorcentajes}%, deben sumar 100%.";
        }

        $baseTotal = (int) ($data->cabecera['base_imponible'] ?? 0);
        if (! empty($data->lineas) && abs($sumaBase - $baseTotal) > count($data->lineas)) {
            $errores[] = "Descuadre en base imponible: cabecera declara {$baseTotal} pero suma de propietarios es {$sumaBase}.";
        }

        return $errores;
    }

    public function formatearArchivo(DjData $data): string
    {
        $sanitizar = fn (string $valor): string =>
            trim(str_replace(';', ',', $valor));

        $lineas = [];

        // Registro A — Cabecera empresa
        $lineas[] = implode(';', [
            'A',
            $sanitizar((string) ($data->cabecera['rut_empresa'] ?? '')),
            $sanitizar((string) ($data->cabecera['razon_social'] ?? '')),
            (string) $data->anio,
            (string) ($data->cabecera['base_imponible'] ?? 0),
            (string) ($data->cabecera['ppm_total'] ?? 0),
        ]);

        // Registros D — Un registro por propietario
        foreach ($data->lineas as $linea) {
            $lineas[] = implode(';', [
                'D',
                $sanitizar((string) ($linea->campos['rut_propietario'] ?? '')),
                $sanitizar((string) ($linea->campos['nombre_propietario'] ?? '')),
                number_format((float) ($linea->campos['porcentaje'] ?? 0), 2, '.', ''),
                (string) ($linea->campos['base_atribuida'] ?? 0),
                (string) ($linea->campos['creditos'] ?? 0),
                (string) ($linea->campos['ppm_atribuido'] ?? 0),
            ]);
        }

        // Registro T — Totales
        $sumaBase = array_sum(array_column(array_map(fn ($l) => $l->campos, $data->lineas), 'base_atribuida'));
        $sumaPpm  = array_sum(array_column(array_map(fn ($l) => $l->campos, $data->lineas), 'ppm_atribuido'));

        $lineas[] = implode(';', [
            'T',
            (string) count($data->lineas),
            (string) $sumaBase,
            (string) $sumaPpm,
        ]);

        return implode("\n", $lineas) . "\n";
    }
}
