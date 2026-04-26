<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Models\Factura;
use Illuminate\Support\Facades\DB;
use Exception;

class FacturaService
{
    public function registrarFacturaCompra(array $datos): Factura
    {
        if (!isset($datos['monto_neto']) || $datos['monto_neto'] <= 0) {
            throw new Exception("El monto neto debe ser mayor a 0.");
        }

        $neto = round((float) $datos['monto_neto'], 2);
        $iva = isset($datos['monto_iva']) ? round((float) $datos['monto_iva'], 2) : round($neto * 0.19, 2);
        $bruto = isset($datos['monto_bruto']) ? round((float) $datos['monto_bruto'], 2) : round($neto + $iva, 2);

        if (abs(($neto + $iva) - $bruto) > 0.01) {
            throw new Exception("Inconsistencia tributaria: El Neto + IVA no coincide con el Monto Bruto.");
        }

        return DB::transaction(function () use ($datos, $neto, $iva, $bruto) {
            $existe = Factura::where('proveedor_id', $datos['proveedor_id'])
                ->where('numero_factura', $datos['numero_factura'])
                ->exists();

            if ($existe) {
                throw new Exception("La factura {$datos['numero_factura']} ya se encuentra registrada para este proveedor.");
            }

            $codigoUnico = (int) (time() . rand(100, 999));

            return Factura::create([
                'empresa_id' => $datos['empresa_id'],
                'codigo_unico' => $codigoUnico,
                'proveedor_id' => $datos['proveedor_id'],
                'cuenta_bancaria_id' => $datos['cuenta_bancaria_id'] ?? null,
                'numero_factura' => $datos['numero_factura'],
                'fecha_emision' => $datos['fecha_emision'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'monto_bruto' => $bruto,
                'monto_neto' => $neto,
                'monto_iva' => $iva,
                'estado' => 'REGISTRADA',
                'autorizador_id' => auth()->id() ?? $datos['autorizador_id'] ?? null,
            ]);
        });
    }
}