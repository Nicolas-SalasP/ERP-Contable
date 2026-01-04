<?php
namespace App\Services;

use App\Repositories\ProveedorRepository;
use Exception;

class ProveedorService
{
    private $repository;

    public function __construct()
    {
        $this->repository = new ProveedorRepository();
    }

    public function obtenerTodos()
    {
        return $this->repository->getAll();
    }

    public function buscarPorCodigo($codigo)
    {
        return $this->repository->getByCodigo($codigo);
    }

    public function crearProveedor(array $data)
    {
        if (empty($data['razonSocial'])) {
            throw new Exception("La razón social es obligatoria.");
        }
        if (empty($data['rut']) && $data['paisIso'] === 'CL') {
            throw new Exception("El RUT es obligatorio para proveedores chilenos.");
        }

        $nuevoCodigo = $this->repository->getNextCodigo();
        $data['codigo'] = $nuevoCodigo;

        $id = $this->repository->create($data);

        return ['id' => $id, 'codigo' => $nuevoCodigo];
    }
}