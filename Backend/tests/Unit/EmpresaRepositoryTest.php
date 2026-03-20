<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Repositories\EmpresaRepository;
use PDO;
use PDOStatement;
use ReflectionClass;

class EmpresaRepositoryTest extends TestCase
{
    private $pdoMock;
    private $stmtMock;
    private EmpresaRepository $repository;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->stmtMock = $this->createMock(PDOStatement::class);
        $this->repository = new EmpresaRepository();
        $reflection = new ReflectionClass($this->repository);
        
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->repository, $this->pdoMock);

        $empresaIdProperty = $reflection->getProperty('empresaId');
        $empresaIdProperty->setAccessible(true);
        $empresaIdProperty->setValue($this->repository, 99);
    }

    public function testObtenerPerfilSinIdYsinEmpresaIdRetornaNull(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $prop = $reflection->getProperty('empresaId');
        $prop->setAccessible(true);
        $prop->setValue($this->repository, null);

        $this->assertNull($this->repository->obtenerPerfil());
    }

    public function testObtenerPerfilRetornaEstructuraCompleta(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->expects($this->exactly(3))->method('execute');
        $this->stmtMock->method('fetch')->willReturn(['id' => 99, 'razon_social' => 'Atlas Tech']);
        $this->stmtMock->method('fetchAll')->willReturnOnConsecutiveCalls(
            [['banco' => 'Banco de Chile', 'numero_cuenta' => '1234']],
            [['codigo' => 'ADM', 'nombre' => 'Administración']]
        );

        $perfil = $this->repository->obtenerPerfil();

        $this->assertIsArray($perfil);
        $this->assertEquals('Atlas Tech', $perfil['razon_social']);
        $this->assertCount(1, $perfil['bancos']);
        $this->assertEquals('Banco de Chile', $perfil['bancos'][0]['banco']);
        $this->assertCount(1, $perfil['centros_costo']);
    }

    public function testActualizarDatosEjecutaUpdateCorrectamente(): void
    {
        $datosNuevos = [
            'razon_social' => 'Nueva Razon',
            'giro' => 'Desarrollo de Software',
            'direccion' => 'Av Siempreviva 123',
            'telefono' => '+56911223344',
            'email_contacto' => 'contacto@empresa.cl'
        ];

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE empresas SET'))
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([
                $datosNuevos['razon_social'],
                $datosNuevos['giro'],
                $datosNuevos['direccion'],
                $datosNuevos['telefono'],
                $datosNuevos['email_contacto'],
                99
            ])
            ->willReturn(true);

        $this->assertTrue($this->repository->actualizarDatos($datosNuevos));
    }

    public function testExisteRutLimpiaGuionesYDevuelveBooleano(): void
    {
        $this->pdoMock->expects($this->once())->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with(['76123456K']);

        $this->stmtMock->expects($this->once())->method('fetch')->willReturn(['id' => 1]);

        $this->assertTrue($this->repository->existeRut('76.123.456-K'));
    }

    public function testCrearEmpresaYVincularUsuarioRealizaMultiplesInserciones(): void
    {
        $datos = [
            'empresa_rut' => '11222333-4',
            'empresa_razon_social' => 'Startup SA',
            'giro' => 'Tecnología',
            'direccion' => 'Calle 1',
            'telefono' => '123',
            'regimen_tributario' => '14_A'
        ];

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->pdoMock->expects($this->once())->method('lastInsertId')->willReturn('5');
        $this->stmtMock->expects($this->exactly(6))->method('execute');

        $nuevoId = $this->repository->crearEmpresaYVincularUsuario(15, $datos);

        $this->assertEquals(5, $nuevoId);
    }
}