<?php
namespace App\Services;

use App\Repositories\CuentaBancariaRepository;
use Exception;

class CuentaBancariaService {
    private $repository;

    public function __construct() {
        $this->repository = new CuentaBancariaRepository();
    }

    public function obtenerPorProveedor($proveedorId) {
        return $this->repository->getAllByProveedor($proveedorId);
    }

    public function agregarCuenta(array $data) {
        if (empty($data['proveedorId'])) {
            throw new Exception("Error interno: Falta ID del proveedor.");
        }
        if (empty($data['banco']) || empty($data['numeroCuenta'])) {
            throw new Exception("El nombre del banco y el número de cuenta son obligatorios.");
        }

        return $this->repository->create($data);
    }

    public function eliminarCuenta($id) {
        return $this->repository->delete($id);
    }
}