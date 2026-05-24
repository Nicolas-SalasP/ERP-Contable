<?php

namespace App\Domains\Sii\Services\Emision;

use App\Domains\Sii\Exceptions\DteEstadoInvalidoException;
use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Caf\CafService;
use App\Domains\Sii\Services\Certificado\CertificadoService;
use App\Domains\Sii\Services\Xml\DteSigner;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\SetDte\SetDteBuilder;
use App\Domains\Sii\Services\Xml\SetDte\SetDteSigner;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Orquestador del flujo de emision de un DTE.
 *
 * Dado un SiiDteEmitido en BORRADOR, ejecuta el flujo completo F4.1+F4.2+F4.3
 * y deja el DTE en estado FIRMADO, con su XML persistido en disco + backup
 * cifrado en BD, hash SHA256 calculado, folio CAF marcado como USADO, y log
 * estructurado de auditoria. NO envia al SII (eso es F5).
 *
 * ESTRATEGIA DE CONCURRENCIA Y FOLIOS HUERFANOS
 *
 * El metodo emitir() NO envuelve todo en una unica transaccion porque eso
 * haria que un fallo post-reserva revierta tambien el folio (no quedaria
 * HUERFANO en BD, simplemente desapareceria). En su lugar:
 *
 *   1) Tx corta para lock pesimista + validar precondiciones + verificar
 *      cert activo. Si algo falla aqui, el DTE NO consume folio.
 *
 *   2) reservarSiguienteFolio() en su propia tx (committed): si el flujo
 *      explota despues, el row de folio_uso queda en BD para que el catch
 *      lo marque como HUERFANO.
 *
 *   3) Construccion XML + firmas en memoria (sin tocar BD).
 *
 *   4) Persistir en disco PRIMERO (mas probable que falle por filesystem),
 *      luego tx final que actualiza el DTE y marca folio como USADO.
 *
 *   5) En cualquier excepcion post-reserva: best-effort cleanup
 *      (borrar archivo si fue escrito) y liberar folio como HUERFANO.
 */
class EmitirDteService
{
    public function __construct(
        private readonly CafService $cafService,
        private readonly CertificadoService $certificadoService,
        private readonly DteXmlBuilder $dteXmlBuilder,
        private readonly DteSigner $dteSigner,
        private readonly SetDteBuilder $setDteBuilder,
        private readonly SetDteSigner $setDteSigner
    ) {
    }

    /**
     * @return SiiDteEmitido el DTE actualizado a FIRMADO con XML persistido.
     *
     * @throws DteEstadoInvalidoException          si el DTE no esta en BORRADOR.
     * @throws DteIncompletoException              si el DTE no tiene detalles.
     * @throws \App\Domains\Sii\Exceptions\CertificadoInvalidoException si la empresa no tiene cert activo.
     * @throws \App\Domains\Sii\Exceptions\SinFoliosDisponiblesException si no hay CAF disponible.
     * @throws \App\Domains\Sii\Exceptions\CafInvalidoException si el CAF reservado es inutilizable.
     * @throws \App\Domains\Sii\Exceptions\DteXmlInvalidException si el XML resultante no valida XSD.
     */
    public function emitir(int $dteEmitidoId): SiiDteEmitido
    {
        // Fase 1: lock + validar + verificar cert (sin consumir folio aun).
        DB::transaction(function () use ($dteEmitidoId) {
            $dte = SiiDteEmitido::query()
                ->where('id', $dteEmitidoId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validarPrecondiciones($dte);

            // Verifica cert activo upfront: si no hay, lanzamos antes de
            // reservar folio (evita generar folios HUERFANOS por config faltante).
            $this->certificadoService->extraerParPemDeEmpresa($dte->empresa);
        });

        // Re-cargar el DTE fuera de la tx con sus relaciones (la fase 1
        // cerro la tx; aqui leemos snapshot fresco).
        /** @var SiiDteEmitido $dte */
        $dte = SiiDteEmitido::with(['empresa', 'detalles', 'referencias', 'traslado.madera', 'impuestosAdicionales'])
            ->findOrFail($dteEmitidoId);

        // Fase 2: reservar folio (tx propia → committed inmediatamente).
        $folioUso = $this->cafService->reservarSiguienteFolio($dte->empresa_id, $dte->tipo_dte);
        $caf      = SiiCaf::findOrFail($folioUso->caf_id);

        $disk    = config('sii.storage.disk', 'local');
        $xmlPath = null;

        try {
            // Fase 3: asignar folio reservado al DTE (en memoria, sin save todavia).
            $dte->folio  = $folioUso->folio;
            $dte->caf_id = $folioUso->caf_id;

            // Fase 4: generar XML con TED firmado + firmar Documento + envolver SetDTE.
            $xmlConTed     = $this->dteXmlBuilder->build($dte, $caf);
            $xmlDteFirmado = $this->dteSigner->firmar($xmlConTed, $dte->empresa);
            $setSinFirma   = $this->setDteBuilder->build($dte->empresa, [['dte' => $dte, 'xml' => $xmlDteFirmado]]);
            $envioFirmado  = $this->setDteSigner->firmar($setSinFirma, $dte->empresa);

            // Fase 5: hash SHA256 sobre XML EN CLARO (antes de cifrar).
            $hashSha256 = hash('sha256', $envioFirmado);

            // Fase 6: persistir en disco PRIMERO. Si falla por filesystem,
            // la tx final ni siquiera abre y no hay que rollback de BD.
            $xmlPath = $this->construirPathDeDisco($dte);
            Storage::disk($disk)->put($xmlPath, $envioFirmado);

            // Fase 7: tx final: actualizar DTE + marcar folio USADO atomicamente.
            $dteFresh = DB::transaction(function () use ($dte, $folioUso, $xmlPath, $hashSha256, $envioFirmado) {
                /** @var SiiDteEmitido $dteLock */
                $dteLock = SiiDteEmitido::query()
                    ->where('id', $dte->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Re-chequeo defensivo: si otro worker firmo entre fase 1 y aqui,
                // abortamos sin sobreescribir (el catch lo hara HUERFANO).
                if ($dteLock->estado !== SiiDteEmitido::ESTADO_BORRADOR) {
                    throw DteEstadoInvalidoException::noEsBorrador($dteLock->id, $dteLock->estado);
                }

                $dteLock->folio                = $folioUso->folio;
                $dteLock->caf_id               = $folioUso->caf_id;
                $dteLock->xml_path             = $xmlPath;
                $dteLock->xml_hash_sha256      = $hashSha256;
                $dteLock->xml_completo_cifrado = Crypt::encryptString($envioFirmado);
                $dteLock->estado               = SiiDteEmitido::ESTADO_FIRMADO;
                $dteLock->fecha_firma          = now();
                $dteLock->save();

                $this->cafService->marcarFolioUsado($folioUso->id, $dteLock->id);

                return $dteLock;
            });

            Log::channel('sii')->info('DTE emitido y firmado', [
                'dte_id'          => $dteFresh->id,
                'empresa_id'      => $dteFresh->empresa_id,
                'tipo_dte'        => $dteFresh->tipo_dte,
                'folio'           => $dteFresh->folio,
                'caf_id'          => $dteFresh->caf_id,
                'xml_path'        => $xmlPath,
                'xml_hash_sha256' => $hashSha256,
                'xml_size_bytes'  => strlen($envioFirmado),
                'fecha_firma'     => $dteFresh->fecha_firma?->toIso8601String(),
            ]);

            return $dteFresh->fresh();
        } catch (Throwable $e) {
            // Cleanup best-effort: borrar archivo si fue persistido.
            if ($xmlPath !== null) {
                try {
                    Storage::disk($disk)->delete($xmlPath);
                } catch (Throwable) {
                    // ignore — el log de abajo capturara contexto general.
                }
            }

            // Marcar folio como HUERFANO: el row existe en BD (lo creo la
            // tx de reservarSiguienteFolio que ya commiteo); ahora lo
            // anotamos para auditoria y para no reusarlo.
            try {
                $this->cafService->liberarFolioHuerfano(
                    $folioUso->id,
                    sprintf('Fallo en EmitirDteService::emitir(dte_id=%d): %s', $dteEmitidoId, $e->getMessage())
                );
            } catch (Throwable) {
                // ignore — no podemos hacer mas; rethrow del error original.
            }

            Log::channel('sii')->error('Emision de DTE fallida; folio liberado como HUERFANO.', [
                'dte_id'       => $dteEmitidoId,
                'folio_uso_id' => $folioUso->id,
                'caf_id'       => $folioUso->caf_id,
                'xml_path'     => $xmlPath,
                'exception'    => $e::class,
                'message'      => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function validarPrecondiciones(SiiDteEmitido $dte): void
    {
        if ($dte->estado !== SiiDteEmitido::ESTADO_BORRADOR) {
            throw DteEstadoInvalidoException::noEsBorrador($dte->id, $dte->estado);
        }

        if ($dte->detalles()->count() === 0) {
            throw DteIncompletoException::campoFaltante(
                sprintf('DTE %d no tiene detalles (al menos 1 linea requerida)', $dte->id)
            );
        }
    }

    /**
     * Path estructurado por empresa/anio/mes/tipo_folio. La separacion por
     * mes previene colisiones intra-empresa entre folios de distintos meses
     * (aunque tipo+folio sea unico por empresa). El sufijo "_envio" deja
     * espacio para variantes futuras (ej. "_aec.xml" para AEC en F6).
     */
    private function construirPathDeDisco(SiiDteEmitido $dte): string
    {
        $fecha = $dte->fecha_emision instanceof Carbon
            ? $dte->fecha_emision
            : Carbon::parse($dte->fecha_emision);

        return sprintf(
            'sii/%d/%s/%s/%d_%d_envio.xml',
            $dte->empresa_id,
            $fecha->format('Y'),
            $fecha->format('m'),
            $dte->tipo_dte,
            $dte->folio
        );
    }
}
