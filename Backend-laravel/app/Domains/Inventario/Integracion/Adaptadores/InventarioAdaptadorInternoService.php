<?php

namespace App\Domains\Inventario\Integracion\Adaptadores;

use App\Domains\Inventario\Integracion\Contratos\InventarioAdaptadorInternoInterface;
use App\Domains\Inventario\Models\InventarioEventoIntegracion;

class InventarioAdaptadorInternoService implements InventarioAdaptadorInternoInterface
{
    public function manejar(InventarioEventoIntegracion $evento): void
    {
        // No-op deliberado Fase 18.
        // Otros módulos podrán registrar adaptadores propios sin que Inventario dependa de ellos.
    }

    public function soporta(InventarioEventoIntegracion $evento): bool
    {
        return $evento->modulo_origen === InventarioEventoIntegracion::MODULO_ORIGEN_INVENTARIO;
    }
}
