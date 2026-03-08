<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProveedorRepository;
use App\Repositories\ContabilidadRepository;
use App\Services\AuditoriaService;
use Exception;

class ProveedorService {
    
    private ProveedorRepository $repositorio;
    private ContabilidadRepository $repoContabilidad;

    public function __construct() {
        $this->repositorio = new ProveedorRepository();
        $this->repoContabilidad = new ContabilidadRepository();
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

    public function obtenerFichaProveedor(int $id): array
    {
        $data = $this->repositorio->obtenerFicha360($id);
        if (!$data) {
            throw new Exception("Proveedor no encontrado o no pertenece a esta empresa.");
        }
        return $data;
    }

    public function registrarAnticipo(array $datos): array
    {
        try {
            $this->repositorio->crearSolicitudAnticipo($datos);
            return [
                'success' => true, 
                'mensaje' => 'Solicitud de anticipo registrada. El asiento contable se generará al pagarlo desde la Cartola o Nómina.'
            ];
        } catch (Exception $e) {
            throw $e;
        }
    }
}