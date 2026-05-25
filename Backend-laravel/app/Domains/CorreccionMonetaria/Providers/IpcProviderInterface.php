<?php

namespace App\Domains\CorreccionMonetaria\Providers;
interface IpcProviderInterface
{
    public function getVariacionMensual(int $anio, int $mes): ?float;
    public function getFactorMultiplicador(int $anio, int $mes): ?float;
    public function getVariacionAcumulada(int $anio, int $mesHasta): ?float;
    public function tieneIndice(int $anio, int $mes): bool;
    public function getNombre(): string;
}