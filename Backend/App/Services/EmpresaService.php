<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\EmpresaRepository;
use App\Helpers\JwtHelper;
use App\Repositories\AutenticacionRepository;
use Exception;

class EmpresaService
{
    private EmpresaRepository $repository;

    public function __construct()
    {
        $this->repository = new EmpresaRepository();
    }

    public function obtenerPerfil()
    {
        $perfil = $this->repository->obtenerPerfil();
        if (!$perfil) {
            throw new Exception("No se encontró el perfil.");
        }
        return $perfil;
    }

    public function actualizarDatos(array $data)
    {
        if (empty($data['razon_social']) || empty($data['rut'])) {
            throw new Exception("Datos obligatorios.");
        }
        return $this->repository->actualizarDatos($data);
    }

    public function actualizarLogo($path)
    {
        return $this->repository->actualizarLogo($path);
    }

    public function agregarBanco(array $data)
    {
        return $this->repository->agregarCuenta($data);
    }

    public function eliminarCuenta($id)
    {
        return $this->repository->eliminarCuenta((int) $id);
    }

    public function procesarOnboarding(int $usuarioId, array $data): array
    {
        if (empty($data['empresa_rut']) || empty($data['empresa_razon_social'])) {
            throw new Exception("El RUT y la Razón Social de la empresa son obligatorios.");
        }

        $empresaId = $this->repository->crearEmpresaYVincularUsuario($usuarioId, $data);
        $authRepo = new AutenticacionRepository();
        $usuarioLocal = $authRepo->buscarUsuarioPorId($usuarioId);

        if (!$usuarioLocal) {
            $usuarioLocal = ['id' => $usuarioId, 'email' => '', 'rol_id' => 1, 'empresa_id' => $empresaId];
        }

        $nuevoToken = JwtHelper::generate([
            'id' => $usuarioId,
            'email' => $usuarioLocal['email'],
            'rol_id' => $usuarioLocal['rol_id'],
            'empresa_id' => $empresaId,
            'version_token' => $usuarioLocal['version_token'] ?? 1
        ]);

        return [
            'success' => true,
            'empresa_id' => $empresaId,
            'token' => $nuevoToken,
            'user' => $usuarioLocal,
            'mensaje' => 'Empresa configurada exitosamente.'
        ];
    }

    public function verificarExistenciaRut(string $rut): bool
    {
        $rutLimpio = str_replace(['.', '-'], '', $rut);

        return $this->repository->existeRut($rutLimpio);
    }

    public function getListaBancos(): array
    {
        return $this->repository->obtenerCatalogoBancos();
    }

    public function listarCentrosCosto()
    {
        return $this->repository->listarCentrosCostoFormat();
    }

    public function agregarCentroCosto(array $data)
    {
        if (empty($data['codigo']) || empty($data['nombre'])) {
            throw new Exception("El código y nombre del Centro de Costo son obligatorios.");
        }
        return $this->repository->agregarCentroCosto($data['codigo'], $data['nombre']);
    }

    public function eliminarCentroCosto($id)
    {
        return $this->repository->eliminarCentroCosto((int) $id);
    }
}