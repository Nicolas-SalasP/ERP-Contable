<?php

namespace App\Domains\Sii\Services\Validators;

use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;

/**
 * Valida la cuadratura aritmetica de los montos del DTE al centavo.
 *
 * Reglas (sobre afectos vs exentos):
 *   - Σ(monto_item de detalles AFECTOS) === monto_neto
 *   - Σ(monto_item de detalles EXENTOS) === monto_exento
 *   - monto_neto × (tasa_iva / 100) === iva   (tolerancia ±1 peso por redondeo)
 *   - monto_neto + iva + monto_exento === monto_total  (tolerancia 0)
 *
 * Tipos exentos (34, 41): monto_neto e iva = 0; monto_exento === monto_total.
 *
 * TOLERANCIA: 1 peso CLP en el calculo de IVA. Esto cubre el caso tipico
 * de una factura con varias lineas donde cada precio_unitario produce un
 * iva con decimales y la suma redondeada difiere en 1 peso del calculo
 * global monto_neto * tasa. Otras igualdades se exigen exactas (entero).
 */
class CuadraturaMontosValidator
{
    /** Tolerancia maxima absoluta en pesos para el calculo de IVA. */
    private const TOLERANCIA_IVA = 1;

    /** Tipos donde TODO el monto es exento (sin IVA). */
    private const TIPOS_EXENTOS = [
        SiiDteEmitido::TIPO_FACTURA_EXENTA,   // 34
        SiiDteEmitido::TIPO_BOLETA_EXENTA,    // 41
    ];

    /**
     * @throws DteIncompletoException si los montos no cuadran.
     */
    public function validar(SiiDteEmitido $dte): void
    {
        $detalles = $dte->relationLoaded('detalles') ? $dte->detalles : $dte->detalles()->get();

        $sumaAfectos = 0;
        $sumaExentos = 0;
        foreach ($detalles as $det) {
            /** @var SiiDteEmitidoDetalle $det */
            $monto = (int) round((float) $det->monto_item);
            if ((bool) ($det->exento ?? false)) {
                $sumaExentos += $monto;
            } else {
                $sumaAfectos += $monto;
            }
        }

        $esExento = in_array((int) $dte->tipo_dte, self::TIPOS_EXENTOS, true);

        $mntNetoDte  = (int) round((float) $dte->monto_neto);
        $mntExeDte   = (int) round((float) $dte->monto_exento);
        $ivaDte      = (int) round((float) $dte->iva);
        $totalDte    = (int) round((float) $dte->monto_total);
        $tasaIvaDte  = (float) $dte->tasa_iva;

        $discrepancias = [];

        // Para tipos exentos: monto_neto e iva DEBEN ser 0; monto_exento === monto_total.
        if ($esExento) {
            if ($mntNetoDte !== 0) {
                $discrepancias['monto_neto'] = ['esperado' => 0, 'real' => $mntNetoDte];
            }
            if ($ivaDte !== 0) {
                $discrepancias['iva'] = ['esperado' => 0, 'real' => $ivaDte];
            }
            if ($mntExeDte !== $totalDte) {
                $discrepancias['monto_exento'] = ['esperado' => $totalDte, 'real' => $mntExeDte];
            }
        } else {
            // Afectos: la suma de detalles afectos debe igualar monto_neto.
            if ($sumaAfectos !== $mntNetoDte) {
                $discrepancias['monto_neto'] = ['esperado' => $sumaAfectos, 'real' => $mntNetoDte];
            }

            // Exentos: la suma de detalles exentos debe igualar monto_exento.
            if ($sumaExentos !== $mntExeDte) {
                $discrepancias['monto_exento'] = ['esperado' => $sumaExentos, 'real' => $mntExeDte];
            }

            // IVA: monto_neto × tasa con tolerancia.
            $ivaEsperado = (int) round($mntNetoDte * $tasaIvaDte / 100);
            if (abs($ivaEsperado - $ivaDte) > self::TOLERANCIA_IVA) {
                $discrepancias['iva'] = ['esperado' => $ivaEsperado, 'real' => $ivaDte];
            }

            // Total: suma EXACTA (tolerancia 0). El residuo de tolerancia de
            // IVA se traslada al total, asi que si IVA quedo ±1, total tambien
            // refleja ese delta — y aqui exigimos coherencia interna del DTE.
            $totalEsperado = $mntNetoDte + $ivaDte + $mntExeDte;
            if ($totalEsperado !== $totalDte) {
                $discrepancias['monto_total'] = ['esperado' => $totalEsperado, 'real' => $totalDte];
            }
        }

        if ($discrepancias !== []) {
            throw DteIncompletoException::montosNoCuadran((int) $dte->id, $discrepancias);
        }
    }
}
