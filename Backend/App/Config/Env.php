<?php
declare(strict_types=1);

namespace App\Config;

class Env {
    private static array $variables = [
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'sistema_contable',
        'DB_USER' => 'root',
        'DB_PASS' => '',

        // --- CONFIGURACIÓN DE CORREO (SMTP HOSTING) ---
        'SMTP_HOST' => 'mail.tuempresa.com', 
        'SMTP_USER' => 'contacto@tuempresa.com', 
        'SMTP_PASS' => 'Tu_Contraseña_Segura_123', 
        'SMTP_PORT' => 465, 
        'SMTP_SECURE' => 'ssl', 
        'SMTP_FROM_NAME' => 'ERP Contable - Seguridad',
        'SMTP_FROM_EMAIL' => 'contacto@tuempresa.com'
    ];

    public static function get(string $key) {
        return self::$variables[$key] ?? null;
    }
}