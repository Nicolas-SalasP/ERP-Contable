<?php
namespace App\Controllers;

use App\Config\Database;
use PDO;

class SuscripcionController {
    
    public function procesarPago() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (($data['api_key'] ?? '') !== 'CLAVE_SECRETA_ENTRE_WEB_Y_ERP') {
            http_response_code(403);
            exit;
        }

        $email = $data['email'];
        $monto = $data['monto'];

        $db = Database::getConnection();
        
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $sql = "UPDATE usuarios SET 
                    estado_suscripcion_id = 1, 
                    fecha_fin_suscripcion = DATE_ADD(IF(fecha_fin_suscripcion > NOW(), fecha_fin_suscripcion, NOW()), INTERVAL 1 MONTH) 
                    WHERE id = ?";
            $db->prepare($sql)->execute([$user['id']]);
        } else {
        }

        echo json_encode(['success' => true, 'message' => 'Suscripción actualizada']);
    }
}