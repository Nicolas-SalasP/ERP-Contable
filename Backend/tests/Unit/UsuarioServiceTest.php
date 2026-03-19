<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\UsuarioService;
use App\Repositories\UsuarioRepository;
use Exception;

class UsuarioServiceTest extends TestCase
{
    private UsuarioService $usuarioService;
    private $repositoryMock;

    protected function setUp(): void
    {
        // 1. Mock del Repositorio
        $this->repositoryMock = $this->createMock(UsuarioRepository::class);
        
        // 2. Inyectamos el Mock en el Service usando Reflexión (ya que el repo se instancia en el constructor original)
        $this->usuarioService = new UsuarioService();
        $reflection = new \ReflectionClass($this->usuarioService);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($this->usuarioService, $this->repositoryMock);

        // 3. Variables de entorno falsas para que MailHelper no falle al intentar conectarse a SMTP reales
        $_ENV['MAIL_HOST'] = 'localhost';
        $_ENV['MAIL_PORT'] = '2525';
        $_ENV['MAIL_BIENVENIDA_USER'] = 'test@erp.cl';
        $_ENV['MAIL_BIENVENIDA_PASS'] = 'secret';
    }

    /**
     * @dataProvider proveedorDatosInvalidosInvitacion
     */
    public function testInvitarLanzaExcepcionConDatosInvalidosOcambiados(array $datos, string $mensajeEsperado): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($mensajeEsperado);

        $this->usuarioService->invitar($datos);
    }

    public function proveedorDatosInvalidosInvitacion(): array
    {
        return [
            'Falta email' => [
                ['rol_id' => 2], 
                'El correo y el rol son obligatorios para invitar.'
            ],
            'Falta rol' => [
                ['email' => 'juan@empresa.cl'], 
                'El correo y el rol son obligatorios para invitar.'
            ],
            'Email vacío' => [
                ['email' => '', 'rol_id' => 2], 
                'El correo y el rol son obligatorios para invitar.'
            ],
            'Email sin formato' => [
                ['email' => 'juan_arroba_empresa.cl', 'rol_id' => 2], 
                'El formato del correo electrónico no es válido.'
            ],
        ];
    }

    public function testObtenerRolesDisponiblesRetornaRolesDelRepositorio(): void
    {
        $rolesSimulados = [
            ['id' => 1, 'nombre' => 'Super Admin'],
            ['id' => 2, 'nombre' => 'Vendedor Pro']
        ];

        $this->repositoryMock->expects($this->once())
            ->method('obtenerRoles')
            ->willReturn($rolesSimulados);

        $resultado = $this->usuarioService->obtenerRolesDisponibles();

        $this->assertCount(2, $resultado);
        $this->assertEquals('Vendedor Pro', $resultado[1]['nombre']);
    }

    public function testObtenerRolesDisponiblesRetornaFallbackSiVacio(): void
    {
        $this->repositoryMock->expects($this->once())
            ->method('obtenerRoles')
            ->willReturn([]);

        $resultado = $this->usuarioService->obtenerRolesDisponibles();

        $this->assertCount(4, $resultado);
        $this->assertEquals('Administrador', $resultado[0]['nombre']);
        $this->assertEquals('Auditor (Solo Lectura)', $resultado[3]['nombre']);
    }

    public function testActualizarRolLanzaExcepcionSiFaltaRolId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El nuevo rol es obligatorio.");

        $this->usuarioService->actualizarRol(5, []);
    }

    public function testActualizarRolLanzaExcepcionSiRepositorioFalla(): void
    {
        $this->repositoryMock->expects($this->once())
            ->method('cambiarRolUsuario')
            ->with(5, 3)
            ->willReturn(false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No se pudo actualizar el rol o el usuario no existe en tu empresa.");

        $this->usuarioService->actualizarRol(5, ['rol_id' => 3]);
    }

    public function testActualizarRolRetornaExito(): void
    {
        $this->repositoryMock->expects($this->once())
            ->method('cambiarRolUsuario')
            ->with(10, 2)
            ->willReturn(true);

        $resultado = $this->usuarioService->actualizarRol(10, ['rol_id' => 2]);

        $this->assertTrue($resultado['success']);
        $this->assertEquals('Rol actualizado con éxito.', $resultado['mensaje']);
    }

    public function testEliminarAccesoLanzaExcepcionSiRepositorioFalla(): void
    {
        $this->repositoryMock->expects($this->once())
            ->method('desvincularUsuario')
            ->with(8)
            ->willReturn(false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No se pudo remover al usuario.");

        $this->usuarioService->eliminarAcceso(8);
    }

    public function testEliminarAccesoRetornaExito(): void
    {
        $this->repositoryMock->expects($this->once())
            ->method('desvincularUsuario')
            ->with(8)
            ->willReturn(true);

        $resultado = $this->usuarioService->eliminarAcceso(8);

        $this->assertTrue($resultado['success']);
        $this->assertEquals('Usuario desvinculado de la empresa.', $resultado['mensaje']);
    }
}