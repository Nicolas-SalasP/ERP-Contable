<?php
namespace App\Repositories;

use App\Config\Database;
use App\Middlewares\AuthMiddleware;
use PDO;
use Exception;

class CotizacionRepository
{
    private $db;
    private $empresaId;

    public function __construct()
    {
        $this->db = Database::getConnection();
        try {
            $auth = AuthMiddleware::authenticate();
            $this->empresaId = $auth->empresa_id ?? 1;
        } catch (Exception $e) {
            $this->empresaId = 1;
        }
    }

    public function registrarCotizacion($datos)
    {
        $this->db->beginTransaction();

        try {
            $sql = "INSERT INTO cotizaciones (
                        cliente_id, 
                        nombre_cliente, 
                        fecha_emision, 
                        validez, 
                        total, 
                        es_afecta, 
                        estado_id, 
                        empresa_id, 
                        created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, 
                        (SELECT id FROM estado_cotizaciones WHERE nombre = 'PENDIENTE' LIMIT 1), 
                        ?, 
                        NOW()
                    )";

            $totalInicial = 0;
            $esAfecta = isset($datos['esAfecta']) ? (int) $datos['esAfecta'] : 1;
            $validez = isset($datos['validezDias']) ? (int) $datos['validezDias'] : 15;

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $datos['clienteId'],
                $datos['nombreCliente'],
                $datos['fechaEmision'],
                $validez,
                $totalInicial,
                $esAfecta,
                $this->empresaId
            ]);

            $cotizacionId = $this->db->lastInsertId();

            $sqlDetalle = "INSERT INTO cotizacion_detalles (
                                cotizacion_id, 
                                producto_nombre, 
                                cantidad, 
                                precio_unitario, 
                                subtotal
                            ) VALUES (?, ?, ?, ?, ?)";

            $stmtDetalle = $this->db->prepare($sqlDetalle);
            $totalCalculado = 0;

            foreach ($datos['items'] as $item) {
                $cantidad = (float) $item['cantidad'];
                $precio = (float) $item['precioUnitario'];
                $subtotal = $cantidad * $precio;

                $totalCalculado += $subtotal;

                $stmtDetalle->execute([
                    $cotizacionId,
                    $item['productoNombre'],
                    $cantidad,
                    $precio,
                    $subtotal
                ]);
            }

            $sqlUpdate = "UPDATE cotizaciones SET total = ? WHERE id = ?";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->execute([$totalCalculado, $cotizacionId]);
            $this->db->commit();

            return ['id' => $cotizacionId];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Error al registrar la cotización: " . $e->getMessage());
        }
    }

    public function listar()
    {
        $sql = "SELECT 
                    c.id,
                    c.cliente_id,
                    c.nombre_cliente,
                    c.fecha_emision,
                    c.total,
                    c.es_afecta,
                    c.validez,
                    c.created_at,
                    e.nombre as estado_nombre 
                FROM cotizaciones c
                JOIN estado_cotizaciones e ON c.estado_id = e.id
                WHERE c.empresa_id = ? 
                ORDER BY c.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id)
    {
        $sql = "SELECT c.*, 
                c.es_afecta, 
                c.validez,
                e.nombre as estado_nombre, 
                cl.contacto_email as cliente_email, 
                cl.contacto_telefono as cliente_telefono,
                cl.rut as cliente_rut,
                cl.direccion as cliente_direccion
                FROM cotizaciones c
                JOIN estado_cotizaciones e ON c.estado_id = e.id
                JOIN clientes cl ON c.cliente_id = cl.id
                WHERE c.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cotizacion) {
            $sqlDetalles = "SELECT * FROM cotizacion_detalles WHERE cotizacion_id = ?";
            $stmtDetalles = $this->db->prepare($sqlDetalles);
            $stmtDetalles->execute([$id]);
            $cotizacion['detalles'] = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);
        }

        return $cotizacion;
    }

    public function actualizarEstado($id, $nuevoEstado)
    {
        $sqlEstado = "SELECT id FROM estado_cotizaciones WHERE nombre = ? LIMIT 1";
        $stmtEstado = $this->db->prepare($sqlEstado);
        $stmtEstado->execute([$nuevoEstado]);
        $estado = $stmtEstado->fetch(PDO::FETCH_ASSOC);

        if (!$estado) {
            throw new Exception("El estado '$nuevoEstado' no está configurado en el sistema.");
        }

        $sql = "UPDATE cotizaciones SET 
                estado_id = ? 
                WHERE id = ? AND empresa_id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $estado['id'],
            $id,
            $this->empresaId
        ]);
    }
}