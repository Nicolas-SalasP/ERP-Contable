<?php
namespace App\Services;

use App\Repositories\LibroMayorRepository;
use Exception;

class LibroMayorService {
    private $repository;

    public function __construct() {
        $this->repository = new LibroMayorRepository();
    }

    public function obtenerLibroDiario() {
        return $this->repository->getLibroDiario();
    }

    public function obtenerPlanCuentas() {
        return $this->repository->getPlanCuentas();
    }
}