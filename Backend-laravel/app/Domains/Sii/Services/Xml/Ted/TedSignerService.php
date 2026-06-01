<?php

namespace App\Domains\Sii\Services\Xml\Ted;

use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Services\Caf\CafService;

/**
 * Firma el bloque DD del TED con RSA-SHA1 usando la clave privada (RSASK)
 * del CAF. La firma resultante se inserta en <FRMT algoritmo="SHA1withRSA">.
 *
 * El SII verifica esta firma contra RSAPUBK incluida en el CAF, validando
 * que el emisor del DTE es el legitimo titular del CAF autorizado.
 *
 * NO usa XMLDSig — el TED del SII Chile usa una firma RSA cruda con SHA1
 * codificada en base64. Implementacion con openssl_sign nativo (sin xmlseclibs).
 */
class TedSignerService
{
    public function __construct(private readonly CafService $cafService)
    {
    }

    /**
     * Firma el string DD con RSA-SHA1 y retorna la firma en base64.
     *
     * IMPORTANTE: el byte-exact string que entra aqui es el byte-exact que
     * el SII va a verificar. Si difiere en UN byte del DD que aparece en
     * el XML final del DTE, el SII rechaza el DTE.
     *
     * @param string $ddXml bloque <DD>...</DD> serializado en bytes finales
     *                      (encoding ISO-8859-1 segun spec SII).
     *
     * @throws CafInvalidoException si la clave privada del CAF no es PEM valido
     */
    public function firmarDd(string $ddXml, SiiCaf $caf): string
    {
        $pem = $this->cafService->extraerRsaSk($caf);

        $clavePrivada = @openssl_pkey_get_private($pem);
        if ($clavePrivada === false) {
            throw CafInvalidoException::rsaSkNoLegible((string) openssl_error_string());
        }

        $firmaBinaria = '';
        $ok = openssl_sign($ddXml, $firmaBinaria, $clavePrivada, OPENSSL_ALGO_SHA1);

        // En PHP 8+, $clavePrivada es OpenSSLAsymmetricKey y se libera por GC.
        // openssl_pkey_free esta deprecated y emite warning.
        unset($clavePrivada);

        if (! $ok) {
            throw CafInvalidoException::rsaSkNoLegible(
                'openssl_sign retorno false. Detalle: ' . (string) openssl_error_string()
            );
        }

        return base64_encode($firmaBinaria);
    }

    /**
     * Verifica una firma RSA-SHA1 contra la clave publica del CAF.
     * Utilidad para tests y validacion local; el SII hace su propia verificacion.
     */
    public function verificarFirma(string $ddXml, string $firmaBase64, SiiCaf $caf): bool
    {
        $clavePublica = @openssl_pkey_get_public($caf->rsa_pubk);
        if ($clavePublica === false) {
            return false;
        }

        $firmaBinaria = base64_decode($firmaBase64, true);
        if ($firmaBinaria === false) {
            return false;
        }

        $resultado = openssl_verify($ddXml, $firmaBinaria, $clavePublica, OPENSSL_ALGO_SHA1);
        unset($clavePublica);

        return $resultado === 1;
    }
}
