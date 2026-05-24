<?php

namespace App\Domains\Sii\Services\Certificado;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\CertificadoInvalidoException;
use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use App\Domains\Sii\Support\RutHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CertificadoService
{
    /**
     * Carga y persiste un nuevo certificado para la empresa. El anterior activo
     * pasa a 'cuarentena' (rollback disponible 30d).
     *
     * @throws CertificadoInvalidoException si el .pfx no se puede leer o la passphrase es incorrecta.
     */
    public function cargar(int $empresaId, string $pfxBinary, string $password): SiiCertificadoEmpresa
    {
        $info = $this->leerPfxOFallar($pfxBinary, $password);

        $parsed = openssl_x509_parse($info['cert']);
        if ($parsed === false) {
            throw CertificadoInvalidoException::pfxCorrupto('openssl_x509_parse devolvio false');
        }

        $subjectCn   = $parsed['subject']['CN'] ?? null;
        $issuerCn    = $parsed['issuer']['CN']  ?? null;
        $validoDesde = Carbon::createFromTimestamp($parsed['validFrom_time_t']);
        $validoHasta = Carbon::createFromTimestamp($parsed['validTo_time_t']);
        $fingerprint = openssl_x509_fingerprint($info['cert'], 'sha256') ?: null;
        $subjectRut  = $this->extraerRut($parsed);

        $cert = DB::transaction(function () use (
            $empresaId, $pfxBinary, $password, $subjectRut, $subjectCn,
            $issuerCn, $validoDesde, $validoHasta, $fingerprint
        ) {
            SiiCertificadoEmpresa::query()
                ->where('empresa_id', $empresaId)
                ->where('estado', SiiCertificadoEmpresa::ESTADO_ACTIVO)
                ->update(['estado' => SiiCertificadoEmpresa::ESTADO_CUARENTENA]);

            return SiiCertificadoEmpresa::create([
                'empresa_id'          => $empresaId,
                'pfx_cifrado'         => Crypt::encryptString($pfxBinary),
                'password_cifrada'    => Crypt::encryptString($password),
                'subject_rut'         => $subjectRut,
                'subject_common_name' => $subjectCn,
                'issuer_common_name'  => $issuerCn,
                'valido_desde'        => $validoDesde,
                'valido_hasta'        => $validoHasta,
                'fingerprint_sha256'  => $fingerprint,
                'estado'              => SiiCertificadoEmpresa::ESTADO_ACTIVO,
            ]);
        });

        $this->logearMismatchRutSiCorresponde($empresaId, $subjectRut);

        return $cert;
    }

    /**
     * Devuelve el .pfx y la passphrase descifrados en memoria. Tambien expone
     * el cert PEM y la llave privada PEM, listos para firmar XMLDSig (F4).
     *
     * NUNCA persistir lo retornado por este metodo.
     *
     * @return array{pfx: string, password: string, cert_pem: string, private_key_pem: string}
     */
    public function extraerPlano(SiiCertificadoEmpresa $cert): array
    {
        $pfx      = Crypt::decryptString($cert->pfx_cifrado);
        $password = Crypt::decryptString($cert->password_cifrada);

        $info = $this->leerPfxOFallar($pfx, $password);

        $privateKeyPem = '';
        // OpenSSL 3 requiere openssl.cnf accesible para openssl_pkey_export.
        @openssl_pkey_export($info['pkey'], $privateKeyPem, null, $this->opcionesOpenssl());

        return [
            'pfx'             => $pfx,
            'password'        => $password,
            'cert_pem'        => $info['cert'],
            'private_key_pem' => $privateKeyPem,
        ];
    }

    /**
     * Detecta openssl.cnf accesible para operaciones que lo requieren en
     * OpenSSL 3 (openssl_pkey_export, openssl_csr_*, etc.). Si no se
     * encuentra, retorna [] y deja que OpenSSL use sus defaults.
     */
    private function opcionesOpenssl(): array
    {
        $env = getenv('OPENSSL_CONF');
        if (is_string($env) && $env !== '' && file_exists($env)) {
            return ['config' => $env];
        }

        $candidatos = [
            '/etc/ssl/openssl.cnf',                   // Debian/Ubuntu
            '/usr/local/etc/openssl/openssl.cnf',     // macOS Homebrew
            '/etc/pki/tls/openssl.cnf',               // RHEL/CentOS
            'C:\\xampp\\apache\\conf\\openssl.cnf',   // XAMPP Windows
            'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
        ];

        foreach ($candidatos as $p) {
            if (file_exists($p)) {
                return ['config' => $p];
            }
        }

        return [];
    }

    public function revocar(SiiCertificadoEmpresa $cert): void
    {
        $cert->estado = SiiCertificadoEmpresa::ESTADO_REVOCADO;
        $cert->save();
    }

    /**
     * Re-descifra y re-lee el .pfx con APP_KEY actual. Util tras rotacion
     * de APP_KEY o ante sospecha de corrupcion del registro.
     */
    public function verificarIntegridad(SiiCertificadoEmpresa $cert): bool
    {
        try {
            $this->extraerPlano($cert);

            return true;
        } catch (\Throwable $e) {
            Log::channel('sii')->warning('Verificacion de integridad de certificado fallida.', [
                'cert_id'    => $cert->id,
                'empresa_id' => $cert->empresa_id,
                'exception'  => $e::class,
                'message'    => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array{cert: string, pkey: \OpenSSLAsymmetricKey, extracerts?: array}
     */
    private function leerPfxOFallar(string $pfxBinary, string $password): array
    {
        // Limpiar la cola de errores OpenSSL antes de la operacion para que
        // openssl_error_string() despues refleje SOLO el error de esta llamada.
        while (openssl_error_string() !== false) {
            // drain
        }

        $info = [];
        $ok   = @openssl_pkcs12_read($pfxBinary, $info, $password);

        if (! $ok) {
            $errores  = [];
            while (($linea = openssl_error_string()) !== false) {
                $errores[] = $linea;
            }
            $errorTexto = implode(' | ', $errores);

            // Heuristica: si menciona "mac verify", "PKCS12 routines" + "mac",
            // o si simplemente falla la lectura con password no vacia, es passphrase mala.
            $esPasswordMala = $errorTexto === ''
                || stripos($errorTexto, 'mac verify') !== false
                || stripos($errorTexto, 'maccheck') !== false
                || stripos($errorTexto, 'password') !== false;

            if ($esPasswordMala) {
                throw CertificadoInvalidoException::passwordIncorrecta();
            }

            throw CertificadoInvalidoException::pfxCorrupto($errorTexto);
        }

        if (! isset($info['cert'], $info['pkey'])) {
            throw CertificadoInvalidoException::pfxCorrupto('El PKCS#12 no contiene cert o llave privada.');
        }

        return $info;
    }

    /**
     * Extrae el RUT del subject del certificado. Best-effort:
     * busca en subject.serialNumber, subject.CN y la cadena del DN.
     * Retorna null si no encuentra (no es error: certs sin RUT son validos).
     */
    private function extraerRut(array $parsed): ?string
    {
        $candidatos = [
            $parsed['subject']['serialNumber'] ?? null,
            $parsed['subject']['CN']           ?? null,
            $parsed['name']                    ?? null,
        ];

        foreach ($candidatos as $candidato) {
            if (! is_string($candidato) || $candidato === '') {
                continue;
            }

            if (preg_match('/(\d{1,2}\.?\d{3}\.?\d{3}-[\dkK])/', $candidato, $m)) {
                try {
                    return RutHelper::normalizar($m[1]);
                } catch (\InvalidArgumentException) {
                    continue;
                }
            }
        }

        return null;
    }

    private function logearMismatchRutSiCorresponde(int $empresaId, ?string $subjectRut): void
    {
        if ($subjectRut === null) {
            return;
        }

        $empresa = Empresa::find($empresaId);
        if ($empresa === null) {
            return;
        }

        $rutsAceptables = array_filter([
            $empresa->rut,
            $empresa->rut_representante_legal,
        ]);

        $rutsNormalizados = [];
        foreach ($rutsAceptables as $r) {
            try {
                $rutsNormalizados[] = RutHelper::normalizar($r);
            } catch (\InvalidArgumentException) {
                // ignorar
            }
        }

        if (! in_array($subjectRut, $rutsNormalizados, true)) {
            Log::channel('sii')->warning(
                'RUT del certificado no coincide con la empresa ni con su representante legal.',
                [
                    'empresa_id'                => $empresaId,
                    'subject_rut'               => $subjectRut,
                    'rut_empresa'               => $empresa->rut,
                    'rut_representante_legal'   => $empresa->rut_representante_legal,
                ]
            );
        }
    }
}
