<?php

namespace App\Domains\Inventario\Integracion\Contratos;

use App\Domains\Inventario\Models\InventarioEventoIntegracion;

interface InventarioAdaptadorInternoInterface
{
    /**
     * Procesa o interpreta un evento interno de Inventario sin acoplarse a módulos externos.
     * La implementación base de Fase 18 es no-op y queda preparada para Fase 19/integraciones futuras.
     */
    public function manejar(InventarioEventoIntegracion $evento): void;

    public function soporta(InventarioEventoIntegracion $evento): bool;
}
