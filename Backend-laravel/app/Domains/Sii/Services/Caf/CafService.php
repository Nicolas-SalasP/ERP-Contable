<?php

namespace App\Domains\Sii\Services\Caf;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Exceptions\FolioUsoEstadoInvalidoException;
use App\Domains\Sii\Exceptions\SinFoliosDisponiblesException;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiCafFolioUso;
use App\Domains\Sii\Support\RutHelper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CafService
{
    public function __construct(private readonly CafXmlParser $parser)
    {
    }

    /**
     * Carga y persiste un CAF para una empresa; la RSA privada y el XML quedan cifrados con APP_KEY.
     *
     * @throws CafInvalidoException
     * @throws ModelNotFoundException
     */
    public function cargar(int $empresaId, string $xmlString): SiiCaf
    {
        $parsed = $this->parser->parse($xmlString);

        /** @var Empresa $empresa */
        $empresa = Empresa::findOrFail($empresaId);

        $rutEmpresaNormalizado = $this->normalizarRutEmpresa($empresa->rut);

        if ($parsed['rut_empresa'] !== $rutEmpresaNormalizado) {
            throw CafInvalidoException::rutNoCoincide(
                $parsed['rut_empresa'],
                $rutEmpresaNormalizado
            );
        }

        // El check-then-act (exists + create) debe ser atomico: dos cargas concurrentes del mismo
        // archivo CAF (doble clic, o dos requests API simultaneos) podrian pasar ambas el exists()
        // antes de que la primera complete el create(), duplicando un rango de folios ya timbrado
        // ante el SII (infraccion tributaria real si se reabre un rango ya usado). El lock sobre la
        // fila de Empresa serializa las cargas concurrentes de la MISMA empresa en MySQL/PostgreSQL
        // (SQLite ignora FOR UPDATE, ver comentario de reservarSiguienteFolio mas abajo); el catch de
        // UniqueConstraintViolationException es una segunda capa de defensa contra el mismo escenario,
        // respaldada por el UNIQUE(empresa_id, sii_idk) ya existente en la migracion de sii_caf.
        return DB::transaction(function () use ($empresaId, $parsed) {
            Empresa::where('id', $empresaId)->lockForUpdate()->first();

            if ($this->existeCaf($empresaId, $parsed['sii_idk'])) {
                throw CafInvalidoException::yaExiste($parsed['sii_idk'], $empresaId);
            }

            try {
                $caf = SiiCaf::create([
                    'empresa_id'           => $empresaId,
                    'tipo_dte'             => $parsed['tipo_dte'],
                    'folio_desde'          => $parsed['folio_desde'],
                    'folio_hasta'          => $parsed['folio_hasta'],
                    'folio_actual'         => $parsed['folio_desde'],
                    'folios_usados'        => 0,
                    'folios_huerfanos'     => 0,
                    'fecha_autorizacion'   => $parsed['fecha_autorizacion'],
                    'fecha_vencimiento'    => $parsed['fecha_vencimiento'],
                    'rut_empresa_caf'      => $parsed['rut_empresa'],
                    'razon_social_caf'     => $parsed['razon_social'],
                    'sii_idk'              => $parsed['sii_idk'],
                    'rsa_sk_cifrada'       => Crypt::encryptString($parsed['rsa_sk']),
                    'xml_completo_cifrado' => Crypt::encryptString($parsed['xml_completo']),
                    'rsa_pubk'             => $parsed['rsa_pubk'],
                    'firma_caf'            => $parsed['firma_caf'],
                    'estado'               => SiiCaf::ESTADO_ACTIVO,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Otra transaccion gano la carrera y ya inserto el mismo sii_idk; nunca se llega a
                // tener dos SiiCaf con el mismo rango de folios activo.
                throw CafInvalidoException::yaExiste($parsed['sii_idk'], $empresaId);
            }

            Log::channel('sii')->info('CAF cargado', [
                'caf_id'      => $caf->id,
                'empresa_id'  => $empresaId,
                'tipo_dte'    => $caf->tipo_dte,
                'rango'       => "{$caf->folio_desde}-{$caf->folio_hasta}",
                'sii_idk'     => $caf->sii_idk,
            ]);

            return $caf;
        });
    }

    /** Extraido para poder simular en tests la condicion de carrera (exists() con lectura obsoleta) sin tocar el comportamiento real. */
    protected function existeCaf(int $empresaId, string $siiIdk): bool
    {
        return SiiCaf::query()
            ->where('empresa_id', $empresaId)
            ->where('sii_idk', $siiIdk)
            ->exists();
    }

    /**
     * Reserva el siguiente folio disponible para un tipo de DTE; atomico via DB::transaction + lockForUpdate (SQLite ignora el lock, la logica es correcta en MySQL/PostgreSQL para evitar race conditions).
     *
     * @throws SinFoliosDisponiblesException
     */
    public function reservarSiguienteFolio(int $empresaId, int $tipoDte, ?int $usuarioId = null): SiiCafFolioUso
    {
        // Paso separado y confirmado ANTES de la transaccion de reserva: sin este chequeo un CAF vencido seguia reservando folios indefinidamente (generando DTEs invalidos ante el SII) hasta revocarlo a mano; y si viviera dentro de la misma DB::transaction() de la reserva, un rollback por "sin folios disponibles" deshacia tambien el cambio de estado a VENCIDO.
        $huboVencido = $this->marcarCafsVencidos($empresaId, $tipoDte);

        return DB::transaction(function () use ($empresaId, $tipoDte, $usuarioId, $huboVencido) {
            $caf = SiiCaf::query()
                ->where('empresa_id', $empresaId)
                ->where('tipo_dte', $tipoDte)
                ->where('estado', SiiCaf::ESTADO_ACTIVO)
                ->whereColumn('folio_actual', '<=', 'folio_hasta')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first();

            if ($caf === null) {
                throw $huboVencido
                    ? SinFoliosDisponiblesException::vencido($tipoDte, $empresaId)
                    : SinFoliosDisponiblesException::paraTipo($tipoDte, $empresaId);
            }

            $folio = $caf->folio_actual;

            $folioUso = SiiCafFolioUso::create([
                'caf_id'             => $caf->id,
                'folio'              => $folio,
                'estado'             => SiiCafFolioUso::ESTADO_RESERVADO,
                'reservado_at'       => now(),
                'usuario_reservo_id' => $usuarioId,
            ]);

            $caf->folio_actual = $folio + 1;
            if ($caf->folio_actual > $caf->folio_hasta) {
                $caf->estado = SiiCaf::ESTADO_AGOTADO;
            }
            $caf->save();

            return $folioUso;
        });
    }

    /** Transiciona a VENCIDO cualquier CAF ACTIVO cuya fecha_vencimiento ya paso, para la empresa+tipo dado; retorna true si transiciono al menos uno (solo para un mensaje de error mas claro al caller). */
    private function marcarCafsVencidos(int $empresaId, int $tipoDte): bool
    {
        $afectados = SiiCaf::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_dte', $tipoDte)
            ->where('estado', SiiCaf::ESTADO_ACTIVO)
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', now()->toDateString())
            ->update(['estado' => SiiCaf::ESTADO_VENCIDO]);

        return $afectados > 0;
    }

    /** @throws FolioUsoEstadoInvalidoException si el folio ya no esta RESERVADO bajo el lock (ej. revocado a HUERFANO por otro proceso mientras se firmaba el DTE) */
    public function marcarFolioUsado(int $folioUsoId, int $dteEmitidoId): void
    {
        DB::transaction(function () use ($folioUsoId, $dteEmitidoId) {
            /** @var SiiCafFolioUso $folioUso */
            $folioUso = SiiCafFolioUso::query()->lockForUpdate()->findOrFail($folioUsoId);

            // Re-chequeo bajo lock: sin esto, un folio revocado (HUERFANO) mientras se firmaba el DTE quedaba
            // sobreescrito de vuelta a USADO, emitiendo un DTE valido sobre un folio invalido ante el SII.
            if ($folioUso->estado !== SiiCafFolioUso::ESTADO_RESERVADO) {
                throw FolioUsoEstadoInvalidoException::alMarcarUsado(
                    $folioUso->id,
                    $folioUso->estado,
                    SiiCafFolioUso::ESTADO_RESERVADO
                );
            }

            $folioUso->update([
                'estado'         => SiiCafFolioUso::ESTADO_USADO,
                'dte_emitido_id' => $dteEmitidoId,
                'usado_at'       => now(),
            ]);

            SiiCaf::query()->where('id', $folioUso->caf_id)->increment('folios_usados');
        });
    }

    public function liberarFolioHuerfano(int $folioUsoId, string $razon): void
    {
        DB::transaction(function () use ($folioUsoId, $razon) {
            /** @var SiiCafFolioUso $folioUso */
            $folioUso = SiiCafFolioUso::query()->lockForUpdate()->findOrFail($folioUsoId);

            $folioUso->update([
                'estado'           => SiiCafFolioUso::ESTADO_HUERFANO,
                'liberado_at'      => now(),
                'razon_liberacion' => $razon,
            ]);

            SiiCaf::query()->where('id', $folioUso->caf_id)->increment('folios_huerfanos');
        });

        Log::channel('sii')->warning('Folio CAF marcado como huerfano.', [
            'folio_uso_id' => $folioUsoId,
            'razon'        => $razon,
        ]);
    }

    /**
     * @return array{
     *   total_autorizado: int,
     *   disponibles: int,
     *   usados: int,
     *   huerfanos: int,
     *   cafs_activos: int,
     *   cafs_agotados: int,
     * }
     */
    public function obtenerSaldoPorTipo(int $empresaId, int $tipoDte): array
    {
        $cafs = SiiCaf::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_dte', $tipoDte)
            ->whereIn('estado', [SiiCaf::ESTADO_ACTIVO, SiiCaf::ESTADO_AGOTADO])
            ->get();

        $totalAutorizado = 0;
        $disponibles     = 0;
        $usados          = 0;
        $huerfanos       = 0;
        $activos         = 0;
        $agotados        = 0;

        foreach ($cafs as $caf) {
            $totalAutorizado += ($caf->folio_hasta - $caf->folio_desde + 1);
            $usados          += $caf->folios_usados;
            $huerfanos       += $caf->folios_huerfanos;

            if ($caf->estado === SiiCaf::ESTADO_ACTIVO) {
                $disponibles += $caf->foliosDisponibles();
                $activos++;
            } else {
                $agotados++;
            }
        }

        return [
            'total_autorizado' => $totalAutorizado,
            'disponibles'      => $disponibles,
            'usados'           => $usados,
            'huerfanos'        => $huerfanos,
            'cafs_activos'     => $activos,
            'cafs_agotados'    => $agotados,
        ];
    }

    /** Descifra y retorna la clave privada RSA en PEM (nunca persistir lo retornado); usado por F4 al firmar el TED de un DTE. */
    public function extraerRsaSk(SiiCaf $caf): string
    {
        return Crypt::decryptString($caf->rsa_sk_cifrada);
    }

    /**
     * Revoca un CAF, liberando los folios RESERVADOS como HUERFANO; los folios USADO permanecen intactos (regla SII: lo emitido es legalmente inmutable).
     *
     * @throws \LogicException si el CAF ya estaba revocado
     */
    public function revocar(SiiCaf $caf, string $motivo): void
    {
        if ($caf->estado === SiiCaf::ESTADO_REVOCADO) {
            throw new \LogicException('El CAF ya estaba revocado.');
        }

        $foliosLiberados = 0;

        DB::transaction(function () use ($caf, $motivo, &$foliosLiberados) {
            $reservados = $caf->folios()
                ->where('estado', SiiCafFolioUso::ESTADO_RESERVADO)
                ->lockForUpdate()
                ->get();

            foreach ($reservados as $folioUso) {
                /** @var SiiCafFolioUso $folioUso */
                $this->liberarFolioHuerfano(
                    $folioUso->id,
                    sprintf('CAF revocado: %s', $motivo)
                );
            }

            $caf->update(['estado' => SiiCaf::ESTADO_REVOCADO]);

            $foliosLiberados = $reservados->count();
        });

        Log::channel('sii')->info('CAF revocado', [
            'caf_id'                          => $caf->id,
            'empresa_id'                      => $caf->empresa_id,
            'tipo_dte'                        => $caf->tipo_dte,
            'motivo'                          => $motivo,
            'folios_liberados_como_huerfano'  => $foliosLiberados,
        ]);
    }

    private function normalizarRutEmpresa(string $rut): string
    {
        try {
            return RutHelper::normalizar($rut);
        } catch (\InvalidArgumentException) {
            // Si el rut de la empresa esta mal formado en BD, retornar el original para que el match con el CAF falle de forma evidente.
            return $rut;
        }
    }
}
