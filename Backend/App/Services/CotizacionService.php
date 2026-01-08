<?php
namespace App\Services;

use App\Repositories\CotizacionRepository;
use Exception;

class CotizacionService
{
    private $repo;

    public function __construct()
    {
        $this->repo = new CotizacionRepository();
    }

    public function registrarCotizacion($datos)
    {
        if (empty($datos['clienteId']) || empty($datos['items'])) {
            throw new Exception("Datos incompletos: Cliente e ítems son obligatorios.");
        }

        foreach ($datos['items'] as $item) {
            if ($item['cantidad'] <= 0 || $item['precioUnitario'] < 0) {
                throw new Exception("Cantidad o precio inválido en uno de los ítems.");
            }
        }
        return $this->repo->registrarCotizacion($datos);
    }

    public function cambiarEstado($id, $nuevoEstado)
    {
        $estadosPermitidos = ['PENDIENTE', 'ACEPTADA', 'ANULADA', 'RECHAZADA'];
        $estadoUpper = strtoupper($nuevoEstado);

        if (!in_array($estadoUpper, $estadosPermitidos)) {
            throw new Exception("El estado '$nuevoEstado' no es válido.");
        }

        $res = $this->repo->actualizarEstado((int) $id, $estadoUpper);

        if (!$res) {
            throw new Exception("No se pudo actualizar el estado de la cotización #$id.");
        }

        return true;
    }

    public function obtenerHistorial()
    {
        return $this->repo->listar();
    }

    public function obtenerDetalleCompleto($id)
    {
        return $this->repo->obtenerPorId($id);
    }
}