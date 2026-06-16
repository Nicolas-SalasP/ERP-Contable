<?php

namespace App\Domains\Contabilidad\DataTransfer;

readonly class DjLineaData
{
    public function __construct(
        public array $campos,
    ) {}
}
