<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Siembra contadores_empresa con el maximo correlativo de OC ya utilizado
 * por cada (empresa_id, anio), a partir de las ordenes de compra existentes.
 *
 * Motivo: OrdenCompraService::generarNumeroOc() paso a usar
 * ContadorEmpresaService::siguienteNumero($empresaId, "orden_compra_{anio}")
 * en vez de calcular el maximo con MAX(SUBSTR(numero_oc,-4)) sin lock. Si el
 * contador de un (empresa_id, anio) que ya tiene OCs no se siembra con su
 * maximo real, la primera llamada nueva a siguienteNumero() empezaria en 1 y
 * colisionaria contra el unique constraint (empresa_id, numero_oc) de una OC
 * preexistente, produciendo un 500.
 *
 * El calculo se hace en PHP (no con SQL especifico de motor) recorriendo
 * todas las filas de ordenes_compra, incluyendo las soft-deleted: el unique
 * constraint de la tabla no excluye soft-deletes, asi que su numero_oc
 * tambien ocupa espacio en la secuencia.
 *
 * El anio se toma de created_at, igual que la version anterior (con bug) de
 * generarNumeroOc(), para no alterar el criterio de agrupacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        /** @var array<int, array<int, int>> $maximos [empresa_id][anio] => maximo correlativo */
        $maximos = [];

        DB::table('ordenes_compra')
            ->select('empresa_id', 'numero_oc', 'created_at')
            ->orderBy('id')
            ->chunk(500, function ($filas) use (&$maximos) {
                foreach ($filas as $fila) {
                    $sufijo = substr((string) $fila->numero_oc, -4);
                    if (! ctype_digit($sufijo)) {
                        continue;
                    }

                    $correlativo = (int) $sufijo;
                    $empresaId = (int) $fila->empresa_id;
                    $anio = Carbon::parse($fila->created_at)->year;

                    $actual = $maximos[$empresaId][$anio] ?? 0;
                    if ($correlativo > $actual) {
                        $maximos[$empresaId][$anio] = $correlativo;
                    }
                }
            });

        $ahora = now();

        foreach ($maximos as $empresaId => $porAnio) {
            foreach ($porAnio as $anio => $maximo) {
                $tipo = "orden_compra_{$anio}";

                $existente = DB::table('contadores_empresa')
                    ->where('empresa_id', $empresaId)
                    ->where('tipo', $tipo)
                    ->first();

                if ($existente) {
                    if ($maximo > (int) $existente->ultimo_valor) {
                        DB::table('contadores_empresa')
                            ->where('id', $existente->id)
                            ->update([
                                'ultimo_valor' => $maximo,
                                'updated_at' => $ahora,
                            ]);
                    }

                    continue;
                }

                DB::table('contadores_empresa')->insert([
                    'empresa_id' => $empresaId,
                    'tipo' => $tipo,
                    'ultimo_valor' => $maximo,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Migracion de datos: no reversible de forma segura, porque no se
        // puede distinguir un contador sembrado por esta migracion de uno
        // incrementado organicamente despues por siguienteNumero().
    }
};
