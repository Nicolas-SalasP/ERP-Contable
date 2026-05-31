<?php

namespace Tests\Concerns;

/**
 * Genera pares RSA reales para tests que necesitan openssl_sign/verify.
 *
 * Razon de existir: en XAMPP/Windows, openssl_pkey_new() falla si no encuentra
 * openssl.cnf. Centralizamos aqui la deteccion de la ruta del config para que
 * todos los tests del dominio SII puedan generar pares sin duplicar logica.
 *
 * En CI/Linux openssl.cnf se ubica automaticamente, pero pasamos el config
 * de XAMPP solo si existe (no rompe entornos donde openssl ya esta configurado).
 */
trait GeneraParRsaParaTests
{
    /**
     * @return array{0: string, 1: string} [pemPrivado, pemPublico]
     */
    protected function generarParRsa(int $bits = 2048): array
    {
        $opciones = [
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $configPath = $this->localizarOpensslCnf();
        if ($configPath !== null) {
            $opciones['config'] = $configPath;
        }

        $res = openssl_pkey_new($opciones);
        if ($res === false) {
            $this->fail(
                'No se pudo generar par RSA. openssl_error_string: ' . (string) openssl_error_string()
            );
        }

        openssl_pkey_export($res, $pemPrivado, null, $configPath !== null ? ['config' => $configPath] : null);
        $detalle = openssl_pkey_get_details($res);

        return [$pemPrivado, $detalle['key']];
    }

    private function localizarOpensslCnf(): ?string
    {
        // Permite override explicito por env var en CI/dev.
        $env = getenv('OPENSSL_CONF');
        if ($env !== false && $env !== '' && is_file($env)) {
            return $env;
        }

        // Ruta relativa al openssl.cnf incluido en el proyecto (funciona en cualquier OS)
        $proyectoCnf = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'openssl.cnf';

        $candidatos = [
            $proyectoCnf,
            'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
            'C:\\xampp\\apache\\conf\\openssl.cnf',
            'C:\\laragon\\bin\\php\\php8.2.26\\extras\\ssl\\openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
        ];

        foreach ($candidatos as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }
}
