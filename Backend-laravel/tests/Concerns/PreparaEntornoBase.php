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

    /**
     * Crea los roles base con jerarquias correctas.
     *
     * Fase 20 / Hardening SII:
     * Los endpoints SII ahora validan permisos granulares mediante middleware.
     * Por eso los roles de prueba que representan usuarios operativos reales
     * deben traer permisos coherentes con su perfil. El rol Usuario se mantiene
     * sin permisos para conservar tests negativos de autorizacion.
     */
    protected function prepararRoles(): void
    {
        $this->rolSuperAdmin = Rol::create([
            'nombre' => 'Super Admin',
            'jerarquia' => 100,
            'permisos' => [],
        ]);
        $this->rolAdministrador = Rol::create([
            'nombre' => 'Administrador',
            'jerarquia' => 80,
            'permisos' => $this->permisosSiiAdministracion(),
        ]);
        $this->rolContador = Rol::create([
            'nombre' => 'Contador',
            'jerarquia' => 50,
            'permisos' => $this->permisosSiiOperacion(),
        ]);
        $this->rolAuditor = Rol::create([
            'nombre' => 'Auditor',
            'jerarquia' => 50,
            'permisos' => $this->permisosSiiSoloLectura(),
        ]);
        $this->rolUsuarioBasico = Rol::create([
            'nombre' => 'Usuario',
            'jerarquia' => 10,
            'permisos' => [],
        ]);
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
