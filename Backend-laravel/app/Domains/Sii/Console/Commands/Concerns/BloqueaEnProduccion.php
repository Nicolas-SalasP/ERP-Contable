<?php

namespace App\Domains\Sii\Console\Commands\Concerns;

/**
 * Comandos "prueba" del dominio Sii consumen recursos reales e irreversibles (folios CAF
 * timbrados, tokens/sesión real del SII) -- sin este guard, correrlos por error en producción
 * (deploy mal configurado, acceso a artisan) quema un folio legal con datos ficticios.
 */
trait BloqueaEnProduccion
{
    protected function abortarSiProduccion(): bool
    {
        if (!$this->laravel->environment('production')) {
            return false;
        }

        $this->error('Este comando es solo para desarrollo/pruebas y está deshabilitado en producción.');

        return true;
    }
}
