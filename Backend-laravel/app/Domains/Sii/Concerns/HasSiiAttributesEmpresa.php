<?php

namespace App\Domains\Sii\Concerns;

/**
 * Extiende Empresa con los campos exigidos por XSD del SII.
 *
 * Auto-aplicado por Eloquent via initialize<Trait>() en cada instancia.
 * NO sobrescribe $fillable ni $casts existentes: merge aditivo.
 */
trait HasSiiAttributesEmpresa
{
    public function initializeHasSiiAttributesEmpresa(): void
    {
        $this->fillable = array_merge($this->fillable ?? [], [
            'giro_emisor',
            'codigo_actividad_sii',
            'comuna',
            'ciudad',
            'resolucion_sii_numero',
            'resolucion_sii_fecha',
            'ambiente_sii',
            'email_intercambio_sii',
            'rut_representante_legal',
        ]);

        $this->casts = array_merge($this->casts ?? [], [
            'resolucion_sii_fecha'  => 'date',
            'codigo_actividad_sii'  => 'integer',
            'resolucion_sii_numero' => 'integer',
        ]);
    }
}
