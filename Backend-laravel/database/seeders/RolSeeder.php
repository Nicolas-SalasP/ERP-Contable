<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Core\Models\Rol;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'nombre' => 'Super Admin',
                'jerarquia' => 100,
                'permisos' => array_values(array_unique(array_merge(
                    $this->permisosOperativosCompletos(),
                    $this->permisosAdministracion(),
                    $this->permisosInventarioCompletos(),
                ))),
            ],
            [
                'id' => 2,
                'nombre' => 'Administrador',
                'jerarquia' => 80,
                'permisos' => array_values(array_unique(array_merge(
                    $this->permisosOperativosCompletos(),
                    $this->permisosInventarioCompletos(),
                ))),
            ],
            [
                'id' => 3,
                'nombre' => 'Contador',
                'jerarquia' => 50,
                'permisos' => array_values(array_unique(array_merge(
                    [
                        'tesoreria.ver', 'tesoreria.crear',
                        'contabilidad.ver', 'contabilidad.crear',
                        'tributario.ver', 'tributario.crear',
                        'activos.ver',
                    ],
                    $this->permisosInventarioCompletos(),
                ))),
            ],
            [
                'id' => 4,
                'nombre' => 'Auditor',
                'jerarquia' => 50,
                'permisos' => array_values(array_unique(array_merge(
                    [
                        'ventas.ver', 'clientes.ver', 'compras.ver', 'proveedores.ver',
                        'tesoreria.ver', 'contabilidad.ver', 'activos.ver', 'tributario.ver',
                        'usuarios.ver',
                    ],
                    $this->permisosInventarioSoloLectura(),
                ))),
            ],
        ];

        foreach ($roles as $rol) {
            Rol::updateOrCreate(['id' => $rol['id']], $rol);
        }
    }

    private function permisosOperativosCompletos(): array
    {
        return [
            'ventas.ver', 'ventas.crear',
            'clientes.ver', 'clientes.crear',
            'compras.ver', 'compras.crear',
            'proveedores.ver', 'proveedores.crear',
            'tesoreria.ver', 'tesoreria.crear',
            'contabilidad.ver', 'contabilidad.crear',
            'activos.ver', 'activos.crear',
            'tributario.ver', 'tributario.crear',
        ];
    }

    private function permisosAdministracion(): array
    {
        return [
            'usuarios.ver', 'usuarios.gestionar',
        ];
    }

    private function permisosInventarioCompletos(): array
    {
        return [
            // Catalogos, productos, bodegas
            'inventario.productos.ver',
            'inventario.productos.crear',
            'inventario.productos.editar',
            'inventario.bodegas.ver',
            'inventario.bodegas.crear',

            // Movimientos y Kardex
            'inventario.movimientos.ver',
            'inventario.movimientos.entrada',
            'inventario.movimientos.salida',
            'inventario.movimientos.traspaso',
            'inventario.movimientos.ajuste',
            'inventario.kardex.ver',

            // PMP y valorizacion
            'inventario.valorizacion.ver',

            // Mermas y ajustes criticos
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',

            // Lotes y trazabilidad
            'inventario.lotes.ver',
            'inventario.lotes.crear',
            'inventario.lotes.editar',

            // Reservas y disponibilidad
            'inventario.reservas.ver',
            'inventario.reservas.crear',
            'inventario.reservas.cancelar',
            'inventario.reservas.liberar',
            'inventario.reservas.consumir',
            'inventario.disponibilidad.ver',

            // Toma fisica
            'inventario.tomas_fisicas.ver',
            // Operaciones logísticas: picking, packing, despachos y devoluciones
            'inventario.ubicaciones.ver',
            'inventario.ubicaciones.crear',
            'inventario.ubicaciones.editar',
            'inventario.stock_ubicaciones.ver',
            'inventario.stock_ubicaciones.mover',
            'inventario.putaway.ejecutar',
            'inventario.picking.ver',
            'inventario.picking.crear',
            'inventario.picking.editar',
            'inventario.picking.confirmar',
            'inventario.picking.cancelar',
            'inventario.packing.ver',
            'inventario.packing.crear',
            'inventario.packing.editar',
            'inventario.packing.confirmar',
            'inventario.packing.cancelar',
            'inventario.despachos.ver',
            'inventario.despachos.crear',
            'inventario.despachos.editar',
            'inventario.despachos.confirmar',
            'inventario.despachos.cancelar',
            'inventario.devoluciones.ver',
            'inventario.devoluciones.crear',
            'inventario.devoluciones.editar',
            'inventario.devoluciones.confirmar',
            'inventario.devoluciones.cancelar',
            'inventario.auditoria.ver',
            'inventario.eventos_integracion.ver',
            'inventario.tomas_fisicas.crear',
            'inventario.tomas_fisicas.contar',
            'inventario.tomas_fisicas.cerrar',
            'inventario.tomas_fisicas.ajustar',
            'inventario.tomas_fisicas.cancelar',
        ];
    }

    private function permisosInventarioSoloLectura(): array
    {
        return [
            'inventario.productos.ver',
            'inventario.bodegas.ver',
            'inventario.movimientos.ver',
            'inventario.kardex.ver',
            'inventario.valorizacion.ver',
            'inventario.ajustes_criticos.ver',
            'inventario.lotes.ver',
            'inventario.reservas.ver',
            'inventario.disponibilidad.ver',
            'inventario.tomas_fisicas.ver',
            // Operaciones logísticas: picking, packing, despachos y devoluciones
            'inventario.ubicaciones.ver',
            'inventario.ubicaciones.crear',
            'inventario.ubicaciones.editar',
            'inventario.stock_ubicaciones.ver',
            'inventario.stock_ubicaciones.mover',
            'inventario.putaway.ejecutar',
            'inventario.picking.ver',
            'inventario.picking.crear',
            'inventario.picking.editar',
            'inventario.picking.confirmar',
            'inventario.picking.cancelar',
            'inventario.packing.ver',
            'inventario.packing.crear',
            'inventario.packing.editar',
            'inventario.packing.confirmar',
            'inventario.packing.cancelar',
            'inventario.despachos.ver',
            'inventario.despachos.crear',
            'inventario.despachos.editar',
            'inventario.despachos.confirmar',
            'inventario.despachos.cancelar',
            'inventario.devoluciones.ver',
            'inventario.devoluciones.crear',
            'inventario.devoluciones.editar',
            'inventario.devoluciones.confirmar',
            'inventario.devoluciones.cancelar',
            'inventario.auditoria.ver',
            'inventario.eventos_integracion.ver',
        ];
    }
}
