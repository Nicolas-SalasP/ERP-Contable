<?php

namespace App\Domains\Rrhh\DataTransfer;

readonly class LreData
{
    public function __construct(
        public int    $empresaId,
        public string $rutEmpresa,
        public string $razonSocial,
        public int    $anio,
        public int    $mes,
        public int    $cantidadTrabajadores,
        /** @var LreLineaData[] */
        public array  $lineas,
    ) {}
}
