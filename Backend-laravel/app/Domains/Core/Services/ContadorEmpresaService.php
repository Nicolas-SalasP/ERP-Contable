<?php

namespace App\Domains\Core\Services;

use Illuminate\Support\Facades\DB;
use Exception;

class ContadorEmpresaService
{
    public function siguienteNumero(int $empresaId, string $tipo): int
    {
        return DB::transaction(function () use ($empresaId, $tipo) {
            DB::table('contadores_empresa')->insertOrIgnore([
                'empresa_id' => $empresaId,
                'tipo' => $tipo,
                'ultimo_valor' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $contador = DB::table('contadores_empresa')
                ->where('empresa_id', $empresaId)
                ->where('tipo', $tipo)
                ->lockForUpdate()
                ->first();

            if (!$contador) {
                throw new Exception(
                    "Contador para empresa {$empresaId} tipo {$tipo} no existe ni pudo crearse."
                );
            }

            $siguienteValor = (int) $contador->ultimo_valor + 1;

            DB::table('contadores_empresa')
                ->where('id', $contador->id)
                ->update([
                    'ultimo_valor' => $siguienteValor,
                    'updated_at' => now(),
                ]);

            return $siguienteValor;
        });
    }

    public function ultimoNumeroAsignado(int $empresaId, string $tipo): int
    {
        $contador = DB::table('contadores_empresa')
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->first();

        return $contador ? (int) $contador->ultimo_valor : 0;
    }
}
