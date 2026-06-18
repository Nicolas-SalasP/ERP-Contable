<?php

namespace App\Domains\Contabilidad\Contracts;

use App\Domains\Contabilidad\DataTransfer\DjData;

interface DeclaracionJuradaContract
{
    public function codigo(): string;

    public function construir(int $empresaId, int $anio): DjData;

    public function validar(DjData $data): array;

    public function formatearArchivo(DjData $data): string;
}
