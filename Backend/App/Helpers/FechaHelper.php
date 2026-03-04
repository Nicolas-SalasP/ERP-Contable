<?php
namespace App\Helpers;
use DateTime;
class FechaHelper
{
    public static function calcularVencimientoHabil($fechaInicio, $diasHabiles)
    {
        $fecha = new DateTime($fechaInicio);
        $diasSumados = 0;
        $anioInicio = (int)$fecha->format('Y');
        $feriados = array_merge(
            self::getFeriadosChile($anioInicio),
            self::getFeriadosChile($anioInicio + 1)
        );

        while ($diasSumados < $diasHabiles) {
            $fecha->modify('+1 day');
            $diaSemana = $fecha->format('N');
            if ($diaSemana >= 6) {
                continue;
            }
            $fechaString = $fecha->format('Y-m-d');
            if (in_array($fechaString, $feriados)) {
                continue;
            }
            $diasSumados++;
        }

        return $fecha->format('Y-m-d');
    }

    private static function getFeriadosChile($year)
    {
        $feriados = [
            // Feriados Fijos (Irrenunciables y Religiosos fijos)
            "{$year}-01-01", // Año Nuevo
            "{$year}-05-01", // Día del Trabajo
            "{$year}-05-21", // Día de las Glorias Navales
            "{$year}-06-20", // Día Nacional de los Pueblos Indígenas (Aprox, puede variar por decreto)
            "{$year}-06-29", // San Pedro y San Pablo
            "{$year}-07-16", // Día de la Virgen del Carmen
            "{$year}-08-15", // Asunción de la Virgen
            "{$year}-09-18", // Independencia Nacional (Fiestas Patrias)
            "{$year}-09-19", // Día de las Glorias del Ejército
            "{$year}-10-12", // Encuentro de Dos Mundos
            "{$year}-10-31", // Día de las Iglesias Evangélicas (Puede moverse si cae martes/miercoles)
            "{$year}-11-01", // Día de Todos los Santos
            "{$year}-12-08", // Inmaculada Concepción
            "{$year}-12-25", // Navidad
        ];
        $timestampPascua = easter_date($year);
        $viernesSanto = date('Y-m-d', strtotime('-2 days', $timestampPascua));
        $sabadoSanto = date('Y-m-d', strtotime('-1 days', $timestampPascua));

        $feriados[] = $viernesSanto;
        $feriados[] = $sabadoSanto;

        return $feriados;
    }
}
?>