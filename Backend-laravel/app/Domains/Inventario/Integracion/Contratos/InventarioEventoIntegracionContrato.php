<?php

namespace App\Domains\Inventario\Integracion\Contratos;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\InventarioEventoIntegracion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface InventarioEventoIntegracionContrato
{
    public function publicarEvento(?User $usuario, array $datos, bool $silencioso = false): ?InventarioEventoIntegracion;

    public function publicarDesdeRequest(Request $request, string $evento, array $datos, bool $silencioso = false): ?InventarioEventoIntegracion;

    public function publicarDesdeOperacion(?User $usuario, string $evento, array $datos, bool $silencioso = true): ?InventarioEventoIntegracion;

    public function listar(User $usuario, array $filtros = []): LengthAwarePaginator;

    public function obtener(User $usuario, int $id): InventarioEventoIntegracion;

    public function resumen(User $usuario, array $filtros = []): array;

    public function marcarProcesado(User $usuario, int $id): InventarioEventoIntegracion;

    public function marcarIgnorado(User $usuario, int $id, ?string $motivo = null): InventarioEventoIntegracion;

    public function marcarError(User $usuario, int $id, string $mensaje): InventarioEventoIntegracion;
}
