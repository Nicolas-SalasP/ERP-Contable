<?php

namespace App\Domains\Sii\Services\Integridad;

use App\Domains\Sii\Exceptions\IntegridadXmlException;
use App\Domains\Sii\Models\SiiDteEmitido;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * HARDENING-1 R2 — Lee el XML completo del EnvioDTE persistido por F4.4 con
 * verificacion criptografica de integridad y fallback a BD si el disco falla.
 *
 * Estrategia tiered:
 *
 *   1. Lee del disco vía xml_path; recomputa SHA256 y compara con
 *      xml_hash_sha256 de BD.
 *      - Match: retorna el contenido (camino feliz).
 *      - Mismatch o I/O error: pasa al fallback BD.
 *
 *   2. Descifra xml_completo_cifrado; recomputa SHA256.
 *      - Match: retorna el contenido y LOGUEA incidente de disco corrupto.
 *      - Mismatch: lanza IntegridadXmlException (ambas fuentes corruptas).
 *
 * Usa hash_equals() para evitar timing attacks en la comparacion de hashes.
 */
class XmlDteIntegrityService
{
    /**
     * @return string EnvioDTE en claro, byte-identico al firmado.
     *
     * @throws IntegridadXmlException si ambas fuentes estan corruptas
     *         o si el DTE aun no fue firmado.
     */
    public function leerVerificado(int $dteId): string
    {
        $dte = SiiDteEmitido::findOrFail($dteId);

        if ($dte->xml_path === null || $dte->xml_path === '' || $dte->xml_hash_sha256 === null) {
            throw IntegridadXmlException::dteNoEmitido($dteId);
        }

        $disk = config('sii.storage.disk', 'local');

        // -------- Fase 1: intento desde disco --------
        try {
            if (Storage::disk($disk)->exists($dte->xml_path)) {
                $xmlDisco = Storage::disk($disk)->get($dte->xml_path);

                if ($xmlDisco !== null) {
                    $hashDisco = hash('sha256', $xmlDisco);
                    if (hash_equals($dte->xml_hash_sha256, $hashDisco)) {
                        return $xmlDisco;
                    }

                    Log::channel('sii')->warning('XML en disco con hash invalido; fallback a BD.', [
                        'dte_id'        => $dteId,
                        'xml_path'      => $dte->xml_path,
                        'hash_esperado' => $dte->xml_hash_sha256,
                        'hash_disco'    => $hashDisco,
                    ]);
                }
            } else {
                Log::channel('sii')->warning('XML no encontrado en disco; fallback a BD.', [
                    'dte_id'   => $dteId,
                    'xml_path' => $dte->xml_path,
                ]);
            }
        } catch (Throwable $e) {
            Log::channel('sii')->warning('Falla I/O al leer XML del disco; fallback a BD.', [
                'dte_id'   => $dteId,
                'xml_path' => $dte->xml_path,
                'error'    => $e->getMessage(),
            ]);
        }

        // -------- Fase 2: fallback a backup cifrado en BD --------
        if ($dte->xml_completo_cifrado === null || $dte->xml_completo_cifrado === '') {
            throw IntegridadXmlException::ambasFuentesCorruptas($dteId);
        }

        try {
            $xmlBd = Crypt::decryptString($dte->xml_completo_cifrado);
        } catch (Throwable $e) {
            Log::channel('sii')->error('Fallo descifrar xml_completo_cifrado del backup BD.', [
                'dte_id' => $dteId,
                'error'  => $e->getMessage(),
            ]);
            throw IntegridadXmlException::ambasFuentesCorruptas($dteId);
        }

        $hashBd = hash('sha256', $xmlBd);
        if (! hash_equals($dte->xml_hash_sha256, $hashBd)) {
            Log::channel('sii')->error('Backup BD tambien tiene hash invalido. Ambas fuentes corruptas.', [
                'dte_id'        => $dteId,
                'hash_esperado' => $dte->xml_hash_sha256,
                'hash_bd'       => $hashBd,
            ]);
            throw IntegridadXmlException::ambasFuentesCorruptas($dteId);
        }

        Log::channel('sii')->info('XML recuperado desde backup BD tras fallo de disco.', [
            'dte_id' => $dteId,
        ]);

        return $xmlBd;
    }
}
