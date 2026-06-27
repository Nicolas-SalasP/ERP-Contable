<?php

namespace App\Domains\CorreccionMonetaria\Providers;

class IneApiIpcProvider implements IpcProviderInterface
{
    public function getVariacionMensual(int $anio, int $mes): ?float
    {
        $this->lanzarNoImplementado();
        return null;
    }

    public function getFactorMultiplicador(int $anio, int $mes): ?float
    {
        $variacion = $this->getVariacionMensual($anio, $mes);
        if ($variacion === null) {
            return null;
        }
        return 1 + ($variacion / 100);
    }

    public function getVariacionAcumulada(int $anio, int $mesHasta): ?float
    {
        $this->lanzarNoImplementado();
        return null;
    }

    public function tieneIndice(int $anio, int $mes): bool
    {
        return false;
    }

    public function getNombre(): string
    {
        return 'API INE (no implementado aún)';
    }

    private function lanzarNoImplementado(): void
    {
        throw new \RuntimeException(
            'El proveedor API del INE no está implementado aún. ' .
            'Usa el ingreso manual de índices IPC en el módulo de Corrección Monetaria. ' .
            'Para activar la integración, completa IneApiIpcProvider y configura ' .
            'CM_IPC_PROVIDER=api_ine en el .env.'
        );
    }
}