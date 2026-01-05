<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProveedorRepository;
use App\Services\AuditoriaService;
use Exception;

class ProveedorService {
    
    private ProveedorRepository $repositorio;

    public function __construct() {
        $this->repositorio = new ProveedorRepository();
    }

    public function obtenerTodos(): array {
        return $this->repositorio->getAll();
    }

    public function buscarPorCodigo(string $codigo): ?array {
        return $this->repositorio->getByCodigo($codigo);
    }

    public function crearProveedor(array $data): array {
        if (empty($data['razonSocial'])) {
            throw new Exception("La razón social es obligatoria.");
        }
        if (empty($data['rut']) && ($data['paisIso'] ?? 'CL') === 'CL') {
            throw new Exception("El RUT es obligatorio para proveedores nacionales.");
        }
        $nuevoCodigo = $this->repositorio->getNextCodigo(); 
        $data['codigo'] = $nuevoCodigo;

        $id = $this->repositorio->create($data);

        AuditoriaService::registrar(
            'CREAR_PROVEEDOR', 
            'proveedores', 
            (int)$id, 
            null, 
            $data
        );

        return ['id' => $id, 'codigo' => $nuevoCodigo];
    }
}