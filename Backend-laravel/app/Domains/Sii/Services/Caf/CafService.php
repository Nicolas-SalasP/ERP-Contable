<?php

namespace App\Domains\Sii\Services\Caf;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Exceptions\SinFoliosDisponiblesException;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiCafFolioUso;
use App\Domains\Sii\Support\RutHelper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CafService
{
    public function __construct(private readonly CafXmlParser $parser)
    {
    }

    /**
     * Carga y persiste un CAF para una empresa. La RSA privada y el XML
     * quedan cifrados con APP_KEY.
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

        if (SiiCaf::query()
            ->where('empresa_id', $empresaId)
            ->where('sii_idk', $parsed['sii_idk'])
            ->exists()
        ) {
            throw CafInvalidoException::yaExiste($parsed['sii_idk'], $empresaId);
        }

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

        Log::channel('sii')->info('CAF cargado', [
            'caf_id'      => $caf->id,
            'empresa_id'  => $empresaId,
            'tipo_dte'    => $caf->tipo_dte,
            'rango'       => "{$caf->folio_desde}-{$caf->folio_hasta}",
            'sii_idk'     => $caf->sii_idk,
        ]);

        return $caf;
    }

    /**
     * Reserva el siguiente folio disponible para un tipo de DTE.
     * Atomico via DB::transaction + lockForUpdate (SQLite ignora el lock; la
     * logica es correcta en MySQL/PostgreSQL para evitar race conditions).
     *
     * @throws SinFoliosDisponiblesException
     */
    public function reservarSiguienteFolio(int $empresaId, int $tipoDte, ?int $usuarioId = null): SiiCafFolioUso
    {
        return DB::transaction(function () use ($empresaId, $tipoDte, $usuarioId) {
            $caf = SiiCaf::query()
                ->where('empresa_id', $empresaId)
                ->where('tipo_dte', $tipoDte)
                ->where('estado', SiiCaf::ESTADO_ACTIVO)
                ->whereColumn('folio_actual', '<=', 'folio_hasta')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first();

            if ($caf === null) {
                throw SinFoliosDisponiblesException::paraTipo($tipoDte, $empresaId);
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

    public function marcarFolioUsado(int $folioUsoId, int $dteEmitidoId): void
    {
        DB::transaction(function () use ($folioUsoId, $dteEmitidoId) {
            /** @var SiiCafFolioUso $folioUso */
            $folioUso = SiiCafFolioUso::query()->lockForUpdate()->findOrFail($folioUsoId);

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

    /**
     * Descifra y retorna la clave privada RSA en PEM. NUNCA persistir lo retornado.
     * Usado por F4 al firmar el TED de un DTE.
     */
    public function extraerRsaSk(SiiCaf $caf): string
    {
        return Crypt::decryptString($caf->rsa_sk_cifrada);
    }

    private function normalizarRutEmpresa(string $rut): string
    {
        try {
            return RutHelper::normalizar($rut);
        } catch (\InvalidArgumentException) {
            // Si el rut de la empresa esta mal formado en BD, retornar el original
            // para que el match con el CAF falle de forma evidente.
            return $rut;
        }
    }
}
