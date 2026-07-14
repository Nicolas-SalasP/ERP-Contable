<?php

namespace App\Domains\Alertas\Contracts;

use App\Domains\Alertas\Support\CandidatoAlerta;
use Illuminate\Support\Collection;

/**
 * Contrato que implementa cada tipo de alerta. Vive en el dominio dueno del dato que vigila
 * (ej. el evaluador de CxC vencida vive en Comercial), no todos amontonados en Alertas.
 * Cada implementacion recorre TODAS las empresas (el motor corre sin usuario autenticado,
 * asi que EmpresaScope no filtra) y debe fijar empresa_id explicitamente en cada candidato.
 */
interface EvaluadorAlerta
{
    /** Identificador estable del tipo de alerta (coincide con Alerta::tipo). */
    public function tipo(): string;

    /** @return Collection<int, CandidatoAlerta> */
    public function evaluar(): Collection;
}
