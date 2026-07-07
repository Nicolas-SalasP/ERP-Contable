<?php

namespace App\Domains\Rrhh\Services\Lre;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\LreEnvio;
use Illuminate\Support\Facades\Storage;

/** Valida el archivo LRE generado verificando estructura y campos obligatorios; retorna un array de errores descriptivos, vacío = sin errores (VALIDADO). */
class ValidarLreService
{
    public function validar(LreEnvio $lre): array
    {
        if (! $lre->archivo_path) {
            throw RrhhException::regla('El LRE no tiene archivo generado. Genera el LRE primero.');
        }

        if (! Storage::disk('sii_xml')->exists($lre->archivo_path)) {
            throw RrhhException::regla('El archivo LRE no se encuentra en disco. Regenera el LRE.');
        }

        $contenido = Storage::disk('sii_xml')->get($lre->archivo_path);
        $errores   = [];

        $lineas = explode("\n", trim($contenido));

        if (! in_array('## INICIO_LIBRO', $lineas, true)) {
            $errores[] = 'Falta marcador ## INICIO_LIBRO en el archivo.';
        }

        if (! in_array('## FIN_LIBRO', $lineas, true)) {
            $errores[] = 'Falta marcador ## FIN_LIBRO en el archivo.';
        }

        $tieneRutEmpresa = false;
        foreach ($lineas as $linea) {
            if (str_starts_with($linea, 'EMPRESA_RUT|')) {
                $tieneRutEmpresa = true;
                $rut = str_replace('EMPRESA_RUT|', '', $linea);
                if (empty(trim($rut))) {
                    $errores[] = 'El RUT de la empresa está vacío.';
                }
                break;
            }
        }

        if (! $tieneRutEmpresa) {
            $errores[] = 'Falta el campo EMPRESA_RUT en el archivo.';
        }

        // Validar que cada bloque INICIO_TRABAJADOR tenga su FIN_TRABAJADOR
        $aperturas = substr_count($contenido, '## INICIO_TRABAJADOR');
        $cierres   = substr_count($contenido, '## FIN_TRABAJADOR');

        if ($aperturas !== $cierres) {
            $errores[] = "Estructura inconsistente: {$aperturas} bloques INICIO_TRABAJADOR y {$cierres} FIN_TRABAJADOR.";
        }

        if ($aperturas === 0) {
            $errores[] = 'El archivo no contiene registros de trabajadores.';
        }

        // El archivo codifica AFP/ISAPRE no reconocida con el mismo '99' que usa IPS (código real) -- ambiguo desde el texto ya generado, por eso se vuelve a mirar el dato fuente (afp/isapre_nombre) en vez de re-parsear el archivo; antes este validador nunca detectaba una institución previsional corrupta.
        foreach ($this->erroresInstitucionPrevisional($lre) as $error) {
            $errores[] = $error;
        }

        // Validar campos obligatorios por trabajador (RUT y fecha inicio)
        $bloques = $this->extraerBloques($contenido);
        foreach ($bloques as $i => $bloque) {
            $numTrabajador = $i + 1;
            if (! $this->bloqueContieneCampo($bloque, '1101')) {
                $errores[] = "Trabajador #{$numTrabajador}: falta campo 1101 (RUT).";
            }
            if (! $this->bloqueContieneCampo($bloque, '1102')) {
                $errores[] = "Trabajador #{$numTrabajador}: falta campo 1102 (fecha inicio contrato).";
            }
            if (! $this->bloqueContieneCampo($bloque, '2101')) {
                $errores[] = "Trabajador #{$numTrabajador}: falta campo 2101 (sueldo base).";
            }
        }

        return $errores;
    }

    /** @return list<string> */
    private function erroresInstitucionPrevisional(LreEnvio $lre): array
    {
        $errores = [];

        $liquidaciones = Liquidacion::where('empresa_id', $lre->empresa_id)
            ->where('anio', $lre->anio)
            ->where('mes', $lre->mes)
            ->where('estado', Liquidacion::ESTADO_EMITIDA)
            ->with('empleado')
            ->get();

        foreach ($liquidaciones as $liquidacion) {
            /** @var \App\Domains\Rrhh\Models\Empleado|null $empleado */
            $empleado = $liquidacion->empleado;
            if (! $empleado) {
                continue;
            }

            $nombre = trim((string) ($empleado->nombres . ' ' . $empleado->apellido_paterno));

            if (! GenerarLreService::afpReconocida($empleado->afp)) {
                $errores[] = "Trabajador {$nombre}: AFP '{$empleado->afp}' no está en el catálogo reconocido (código 1141 quedaría como '99', ambiguo con IPS).";
            }

            if ($empleado->tipo_salud === 'ISAPRE' && ! GenerarLreService::isapreReconocida($empleado->isapre_nombre)) {
                $errores[] = "Trabajador {$nombre}: ISAPRE '{$empleado->isapre_nombre}' no está en el catálogo reconocido (código 1143 quedaría como '99').";
            }
        }

        return $errores;
    }

    private function extraerBloques(string $contenido): array
    {
        $bloques = [];
        $partes  = explode('## INICIO_TRABAJADOR', $contenido);

        // El primer elemento es el encabezado del libro; los siguientes son bloques de trabajadores
        for ($i = 1; $i < count($partes); $i++) {
            $fin = strpos($partes[$i], '## FIN_TRABAJADOR');
            if ($fin !== false) {
                $bloques[] = substr($partes[$i], 0, $fin);
            }
        }

        return $bloques;
    }

    private function bloqueContieneCampo(string $bloque, string $codigo): bool
    {
        return str_contains($bloque, "\n{$codigo}|") || str_starts_with(trim($bloque), "{$codigo}|");
    }
}
