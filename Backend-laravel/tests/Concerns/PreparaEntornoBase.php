<?php

namespace Tests\Concerns;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;

/**
 * Trait base para preparar el entorno de tests.
 *
 * Centraliza la creacion de catalogos base (EstadoSuscripcion, Pais, Rol)
 * y la creacion de Empresa + Usuario para cada test.
 *
 * Importante: este trait esta disenado para funcionar igual en SQLite y
 * en MySQL. NO asume valores especificos de id, NO usa mass assignment
 * de id en create(), y NO depende del comportamiento de auto increment
 * entre transacciones.
 *
 * Uso tipico en un test:
 *
 *   class MiTest extends TestCase
 *   {
 *       use RefreshDatabase, PreparaEntornoBase;
 *
 *       protected function setUp(): void
 *       {
 *           parent::setUp();
 *           $this->prepararEntornoBase();
 *           // Si necesitas un usuario admin listo:
 *           [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();
 *       }
 *   }
 */
trait PreparaEntornoBase
{
    /** @var EstadoSuscripcion */
    protected $estadoSuscripcionActiva;

    /** @var EstadoSuscripcion|null */
    protected $estadoSuscripcionInactiva;

    /** @var Rol */
    protected $rolSuperAdmin;

    /** @var Rol */
    protected $rolAdministrador;

    /** @var Rol */
    protected $rolContador;

    /** @var Rol */
    protected $rolAuditor;

    /** @var Rol */
    protected $rolUsuarioBasico;

    /** @var Pais */
    protected $paisChile;

    /**
     * Prepara los catalogos base (EstadoSuscripcion, Pais, Roles).
     * Llamar al inicio del setUp() de cada test.
     */
    protected function prepararEntornoBase(): void
    {
        $this->prepararEstadosSuscripcion();
        $this->prepararPaises();
        $this->prepararRoles();
    }

    /**
     * Crea los estados de suscripcion basicos.
     * No asignamos id manualmente: capturamos el id que asigna la BD.
     */
    protected function prepararEstadosSuscripcion(): void
    {
        $this->estadoSuscripcionActiva = EstadoSuscripcion::create([
            'nombre' => 'Activa',
        ]);
        $this->estadoSuscripcionInactiva = EstadoSuscripcion::create([
            'nombre' => 'Inactiva',
        ]);
    }

    /**
     * Crea el pais Chile, que es el default del sistema.
     */
    protected function prepararPaises(): void
    {
        $this->paisChile = Pais::create([
            'iso' => 'CL',
            'nombre' => 'Chile',
            'moneda_defecto' => 'CLP',
            'etiqueta_id' => 'RUT',
            'activo' => true,
        ]);
    }

    protected function prepararRoles(): void
    {
        // Los roles de test reflejan el RolSeeder de produccion para que el RBAC
        // por endpoint (middleware permiso:) se comporte igual que en runtime.
        $this->rolSuperAdmin = Rol::create([
            'nombre' => 'Super Admin',
            'jerarquia' => 100,
            'permisos' => [],
        ]);
        $this->rolAdministrador = Rol::create([
            'nombre' => 'Administrador',
            'jerarquia' => 80,
            'permisos' => array_values(array_unique(array_merge(
                $this->permisosOperativosCompletos(),
                $this->permisosInventarioCompletos(),
                $this->permisosSiiAdministracion(),
                $this->permisosRrhhCompletos(),
                // H15: espejo de RolSeeder — el Administrador declara gestión de usuarios.
                ['usuarios.ver', 'usuarios.gestionar'],
            ))),
        ]);
        $this->rolContador = Rol::create([
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
                $this->permisosSiiOperacion(),
            ))),
        ]);
        $this->rolAuditor = Rol::create([
            'nombre' => 'Auditor',
            'jerarquia' => 50,
            'permisos' => array_values(array_unique(array_merge(
                [
                    'ventas.ver', 'clientes.ver', 'compras.ver', 'proveedores.ver',
                    'tesoreria.ver', 'contabilidad.ver', 'activos.ver', 'tributario.ver',
                    'usuarios.ver',
                ],
                $this->permisosInventarioSoloLectura(),
                $this->permisosSiiSoloLectura(),
            ))),
        ]);
        $this->rolUsuarioBasico = Rol::create([
            'nombre' => 'Usuario',
            'jerarquia' => 10,
            'permisos' => [],
        ]);
    }

    /**
     * Permisos operativos completos (comercial/contabilidad/tesoreria/activos/
     * tributario). Espejo de RolSeeder::permisosOperativosCompletos().
     */
    protected function permisosOperativosCompletos(): array
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

    /**
     * Permisos de inventario completos. Espejo de RolSeeder::permisosInventarioCompletos().
     */
    protected function permisosInventarioCompletos(): array
    {
        return [
            'inventario.dashboard.ver',
            'inventario.reportes.ver', 'inventario.reportes.exportar',
            'inventario.reportes.picking', 'inventario.reportes.packing',
            'inventario.reportes.despachos', 'inventario.reportes.devoluciones',
            'inventario.productos.ver', 'inventario.productos.crear', 'inventario.productos.editar',
            'inventario.bodegas.ver', 'inventario.bodegas.crear',
            'inventario.movimientos.ver', 'inventario.movimientos.entrada',
            'inventario.movimientos.salida', 'inventario.movimientos.traspaso', 'inventario.movimientos.ajuste',
            'inventario.kardex.ver',
            'inventario.valorizacion.ver',
            'inventario.ajustes_criticos.ver', 'inventario.ajustes_criticos.crear',
            'inventario.lotes.ver', 'inventario.lotes.crear', 'inventario.lotes.editar',
            'inventario.reservas.ver', 'inventario.reservas.crear', 'inventario.reservas.cancelar',
            'inventario.reservas.liberar', 'inventario.reservas.consumir',
            'inventario.disponibilidad.ver',
            'inventario.alertas.ver',
            'inventario.reglas_reposicion.ver', 'inventario.reglas_reposicion.crear',
            'inventario.reglas_reposicion.editar', 'inventario.reglas_reposicion.eliminar',
            'inventario.tomas_fisicas.ver', 'inventario.tomas_fisicas.crear', 'inventario.tomas_fisicas.contar',
            'inventario.tomas_fisicas.cerrar', 'inventario.tomas_fisicas.ajustar', 'inventario.tomas_fisicas.cancelar',
            'inventario.ubicaciones.ver', 'inventario.ubicaciones.crear', 'inventario.ubicaciones.editar',
            'inventario.stock_ubicaciones.ver', 'inventario.stock_ubicaciones.mover', 'inventario.putaway.ejecutar',
            'inventario.picking.ver', 'inventario.picking.crear', 'inventario.picking.editar',
            'inventario.picking.confirmar', 'inventario.picking.cancelar',
            'inventario.packing.ver', 'inventario.packing.crear', 'inventario.packing.editar',
            'inventario.packing.confirmar', 'inventario.packing.cancelar',
            'inventario.despachos.ver', 'inventario.despachos.crear', 'inventario.despachos.editar',
            'inventario.despachos.confirmar', 'inventario.despachos.cancelar',
            'inventario.devoluciones.ver', 'inventario.devoluciones.crear', 'inventario.devoluciones.editar',
            'inventario.devoluciones.confirmar', 'inventario.devoluciones.cancelar',
            'inventario.auditoria.ver', 'inventario.auditoria.detalle', 'inventario.auditoria.resumen',
            'inventario.eventos_integracion.ver', 'inventario.eventos_integracion.detalle',
            'inventario.eventos_integracion.resumen', 'inventario.eventos_integracion.procesar',
            'inventario.eventos_integracion.gestionar',
        ];
    }

    /**
     * Permisos de inventario de solo lectura. Espejo de RolSeeder::permisosInventarioSoloLectura().
     */
    protected function permisosInventarioSoloLectura(): array
    {
        return [
            'inventario.dashboard.ver',
            'inventario.reportes.ver',
            'inventario.productos.ver',
            'inventario.bodegas.ver',
            'inventario.movimientos.ver',
            'inventario.kardex.ver',
            'inventario.valorizacion.ver',
            'inventario.ajustes_criticos.ver',
            'inventario.lotes.ver',
            'inventario.reservas.ver',
            'inventario.disponibilidad.ver',
            'inventario.alertas.ver',
            'inventario.tomas_fisicas.ver',
            'inventario.ubicaciones.ver',
            'inventario.stock_ubicaciones.ver',
            'inventario.picking.ver',
            'inventario.packing.ver',
            'inventario.despachos.ver',
            'inventario.devoluciones.ver',
            'inventario.auditoria.ver',
            'inventario.eventos_integracion.ver',
        ];
    }

    /**
     * Permisos SII completos para administracion operativa en tests.
     */
    protected function permisosSiiAdministracion(): array
    {
        return [
            'sii.configuracion.ver',
            'sii.configuracion.editar',

            'sii.certificado.ver',
            'sii.certificado.subir',
            'sii.certificado.revocar',

            'sii.caf.ver',
            'sii.caf.subir',
            'sii.caf.revocar',

            'sii.dte.ver',
            'sii.dte.emitir',
            'sii.dte.reintentar',
            'sii.dte.anular',

            'sii.auditoria.ver',
        ];
    }

    /**
     * Permisos SII para operacion contable/tributaria sin revocaciones sensibles.
     */
    protected function permisosSiiOperacion(): array
    {
        return [
            'sii.configuracion.ver',

            'sii.certificado.ver',

            'sii.caf.ver',

            'sii.dte.ver',
            'sii.dte.emitir',
            'sii.dte.reintentar',

            'sii.auditoria.ver',
        ];
    }

    /**
     * Permisos SII de lectura para auditoria.
     */
    protected function permisosSiiSoloLectura(): array
    {
        return [
            'sii.configuracion.ver',
            'sii.certificado.ver',
            'sii.caf.ver',
            'sii.dte.ver',
            'sii.auditoria.ver',
        ];
    }

    /**
     * Permisos RRHH completos. Espejo de RolSeeder::permisosRrhhCompletos().
     */
    protected function permisosRrhhCompletos(): array
    {
        return [
            'rrhh.empleados.ver',
            'rrhh.empleados.crear',
            'rrhh.empleados.editar',
            'rrhh.contratos.crear',
            'rrhh.remuneraciones.ver',
            'rrhh.remuneraciones.procesar',
            'rrhh.parametros.ver',
            'rrhh.parametros.editar',
        ];
    }

    /**
     * Crea una Empresa con un Usuario administrador asociado.
     *
     * @param array $datosEmpresa Override opcional de campos de la empresa
     * @param array $datosUsuario Override opcional de campos del usuario
     * @return array{0: Empresa, 1: User}
     */
    protected function crearEmpresaConAdmin(array $datosEmpresa = [], array $datosUsuario = []): array
    {
        $empresa = Empresa::create(array_merge([
            'rut' => $this->rutAleatorio(),
            'razon_social' => 'Empresa Test ' . uniqid(),
        ], $datosEmpresa));

        $usuario = User::create(array_merge([
            'nombre' => 'Admin Test',
            'email' => 'admin' . uniqid() . '@test.cl',
            'password' => bcrypt('password123'),
            'empresa_id' => $empresa->id,
            'rol_id' => $this->rolAdministrador->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ], $datosUsuario));

        return [$empresa, $usuario];
    }

    /**
     * Crea una empresa sin usuario (cuando el test maneja sus propios usuarios).
     */
    protected function crearEmpresa(array $datos = []): Empresa
    {
        return Empresa::create(array_merge([
            'rut' => $this->rutAleatorio(),
            'razon_social' => 'Empresa Test ' . uniqid(),
        ], $datos));
    }

    /**
     * Crea un usuario asociado a una empresa y un rol.
     *
     * @param Empresa $empresa
     * @param Rol|null $rol Si null, usa rolAdministrador
     * @param array $overrides Campos extras a sobreescribir
     */
    protected function crearUsuario(Empresa $empresa, ?Rol $rol = null, array $overrides = []): User
    {
        $rol = $rol ?? $this->rolAdministrador;

        return User::create(array_merge([
            'nombre' => 'Usuario Test',
            'email' => 'user' . uniqid() . '@test.cl',
            'password' => bcrypt('password123'),
            'empresa_id' => $empresa->id,
            'rol_id' => $rol->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ], $overrides));
    }

    /**
     * Genera un RUT chileno aleatorio sintacticamente valido (sin verificar DV real).
     * Util cuando varios tests crean empresas y necesitan RUTs distintos.
     */
    protected function rutAleatorio(): string
    {
        $numero = rand(10000000, 99999999);
        $dv = ['0','1','2','3','4','5','6','7','8','9','K'][rand(0, 10)];
        return number_format($numero, 0, '', '.') . '-' . $dv;
    }
}
