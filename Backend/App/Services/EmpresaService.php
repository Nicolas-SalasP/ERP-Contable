<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Repositories\EmpresaRepository;
use PDO;
use Exception;

class EmpresaService {

    private PDO $db;
    private EmpresaRepository $repository;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->repository = new EmpresaRepository(); 
    }


    public function obtenerPerfil() {
        $perfil = $this->repository->obtenerPerfil();
        if (!$perfil) throw new Exception("No se encontró el perfil.");
        return $perfil;
    }

    public function actualizarDatos(array $data) {
        if (empty($data['razon_social']) || empty($data['rut'])) throw new Exception("Datos obligatorios.");
        return $this->repository->actualizarDatos($data);
    }

    public function actualizarLogo($path) {
        return $this->repository->actualizarLogo($path);
    }

    public function agregarBanco(array $data) {
        return $this->repository->agregarCuenta($data);
    }

    public function eliminarCuenta($id) {
        return $this->repository->eliminarCuenta((int)$id);
    }

    public function registrarEmpresaCompleta(array $data): array {
        try {
            $this->db->beginTransaction();

            if ($this->repository->existeRut($data['empresa_rut'])) {
                throw new Exception("El RUT de la empresa ya existe.");
            }
            if ($this->repository->existeEmailUsuario($data['admin_email'])) {
                throw new Exception("El email del administrador ya está en uso.");
            }

            $empresaId = $this->repository->crearEmpresa(
                $data['empresa_rut'], 
                $data['empresa_razon_social']
            );

            $passwordHash = password_hash($data['admin_password'], PASSWORD_BCRYPT);
            
            $this->repository->crearUsuarioAdmin(
                $empresaId,
                $data['admin_nombre'],
                $data['admin_email'],
                $passwordHash
            );

            $this->repository->clonarPlanMaestro($empresaId);
            $this->repository->inicializarSecuencias($empresaId);

            $this->db->commit();

            if (class_exists('App\Services\AuditoriaService')) {
                \App\Services\AuditoriaService::registrar(
                    'REGISTRO_NUEVA_EMPRESA', 'empresas', $empresaId, null, 
                    ['rut' => $data['empresa_rut']]
                );
            }

            return ['success' => true, 'empresa_id' => $empresaId, 'mensaje' => 'Empresa creada exitosamente.'];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new Exception("Error en registro: " . $e->getMessage());
        }
    }

    public function getListaBancos(): array 
    {
        return $this->repository->obtenerCatalogoBancos();
    }
}