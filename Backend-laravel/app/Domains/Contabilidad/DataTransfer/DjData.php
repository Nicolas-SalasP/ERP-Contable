<?php

namespace App\Domains\Contabilidad\DataTransfer;

readonly class DjData
{
    public function __construct(
        public string $codigoDj,
        public int    $empresaId,
        public int    $anio,
        public array  $cabecera,
        /** @var DjLineaData[] */
        public array  $lineas,
    ) {}
}
