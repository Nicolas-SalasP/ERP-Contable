<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\LibroMayorRepository;
use App\Services\AuditoriaService;

class LibroMayorService {
    
    private LibroMayorRepository $repository;

    public function __construct() {
        $this->repository = new LibroMayorRepository();
    }

    public function obtenerLibroDiario(): array {
        AuditoriaService::registrar('GENERAR_LIBRO_DIARIO');

        return $this->repository->getLibroDiario();
    }

    public function obtenerPlanCuentas(): array {
        return $this->repository->getPlanCuentas();
    }
}