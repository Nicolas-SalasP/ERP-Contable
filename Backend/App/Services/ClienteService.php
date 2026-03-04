<?php
namespace App\Services;

use App\Repositories\ClienteRepository;

class ClienteService {
    private $repo;
    public function __construct() { $this->repo = new ClienteRepository(); }

    public function listar($filtro = '') { 
        return $this->repo->buscarFiltrado($filtro); 
    }

    public function registrar($data) { 
        if (empty($data['codigo_cliente'])) {
            $data['codigo_cliente'] = 'CLI-' . str_replace(['.', '-'], '', $data['rut']);
        }
        return $this->repo->create($data); 
    }

    public function editar($id, $data) { 
        return $this->repo->update($id, $data); 
    }

    public function bloquear($id) { 
        return $this->repo->softDelete($id); 
    }
}