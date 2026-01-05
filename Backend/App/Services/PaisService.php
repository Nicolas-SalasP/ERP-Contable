<?php
namespace App\Services;

use App\Repositories\PaisRepository;

class PaisService {
    private $repository;

    public function __construct() {
        $this->repository = new PaisRepository();
    }

    public function listarPaisesActivos() {
        return $this->repository->obtenerTodos();
    }
    
    public function obtenerPorIso($iso) {
        return $this->repository->buscarPorIso($iso);
    }
}