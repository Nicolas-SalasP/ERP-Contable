<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AutenticacionRepository;
use App\Helpers\JwtHelper;       // <--- Recuperado del antiguo
use App\Services\AuditoriaService; // <--- Recuperado del antiguo
use App\Config\Env;              // <--- Del nuevo
use Exception;

// Librerías de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

class AutenticacionService {

    private AutenticacionRepository $repository;

    public function __construct() {
        $this->repository = new AutenticacionRepository();
    }

    // =========================================================================
    // 1. INICIAR SESIÓN (Recuperado y adaptado)
    // =========================================================================
    public function iniciarSesion(string $email, string $password): array {
        $usuario = $this->repository->buscarUsuarioPorEmail($email);

        // 1. Validar Credenciales
        if (!$usuario || !password_verify($password, $usuario['password'])) {
            if (class_exists('App\Services\AuditoriaService')) {
                AuditoriaService::registrar(
                    'LOGIN_FALLIDO', 
                    'usuarios', 
                    null, 
                    null, 
                    ['email_intentado' => $email]
                );
            }
            throw new Exception('CREDENCIALES_INCORRECTAS');
        }

        // 2. Validar Estado de Suscripción (Lógica original tuya)
        if ((int)$usuario['estado_suscripcion_id'] !== 1) {
            throw new Exception('CUENTA_SUSPENDIDA');
        }

        if ($usuario['fecha_fin_suscripcion'] < date('Y-m-d')) {
            throw new Exception('PLAN_VENCIDO');
        }

        // 3. Generar Token JWT
        $token = JwtHelper::generate([
            'id' => $usuario['id'],
            'rol' => $usuario['rol_id'],
            'empresa_id' => $usuario['empresa_id'],
            'nombre' => $usuario['nombre']
        ]);

        // 4. Auditoría de éxito
        if (class_exists('App\Services\AuditoriaService')) {
            AuditoriaService::registrar(
                'LOGIN_EXITOSO', 
                'usuarios', 
                (int)$usuario['id'],
                null, 
                ['empresa_id' => $usuario['empresa_id']]
            );
        }

        // 5. Limpiar password antes de retornar
        unset($usuario['password']);

        return [
            'token' => $token,
            'usuario' => $usuario
        ];
    }

    // =========================================================================
    // 2. RECUPERACIÓN DE CONTRASEÑA (Nueva lógica con PHPMailer)
    // =========================================================================

    public function iniciarRecuperacion(string $email): void 
    {
        // Usamos el mismo método del repo
        $usuario = $this->repository->buscarUsuarioPorEmail($email);
        
        if (!$usuario) {
            return; 
        }

        $codigo = (string)rand(100000, 999999);
        $this->repository->guardarTokenRecuperacion($email, $codigo);
        
        // Enviar correo real
        $this->enviarCorreoRecuperacion($email, $codigo);
    }

    private function enviarCorreoRecuperacion(string $destinatario, string $codigo): void 
    {
        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host       = Env::get('SMTP_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = Env::get('SMTP_USER');
            $mail->Password   = Env::get('SMTP_PASS');
            
            // Seguridad según puerto
            if (Env::get('SMTP_SECURE') === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            }
            
            $mail->Port       = Env::get('SMTP_PORT');

            // Remitente y Destinatario
            $mail->setFrom(Env::get('SMTP_FROM_EMAIL'), Env::get('SMTP_FROM_NAME'));
            $mail->addAddress($destinatario);

            // Contenido
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Código de Recuperación de Contraseña';
            
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
                    <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-top: 4px solid #059669;'>
                        <h2 style='color: #1f2937; text-align: center;'>Recuperación de Acceso</h2>
                        <p style='color: #4b5563; font-size: 16px;'>Hola,</p>
                        <p style='color: #4b5563; font-size: 16px;'>Has solicitado restablecer tu contraseña. Utiliza el siguiente código:</p>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <span style='font-size: 32px; font-weight: bold; letter-spacing: 8px; background-color: #ecfdf5; color: #047857; padding: 15px 30px; border-radius: 8px; border: 1px dashed #10b981; display: inline-block;'>
                                $codigo
                            </span>
                        </div>
                        
                        <p style='color: #4b5563;'>Este código expira en <strong>15 minutos</strong>.</p>
                    </div>
                </div>
            ";
            
            $mail->AltBody = "Tu código de recuperación es: $codigo. Expira en 15 minutos.";

            $mail->send();

        } catch (MailerException $e) {
            error_log("Error PHPMailer: {$mail->ErrorInfo}");
            // throw new Exception("Error al enviar el correo"); // Opcional
        }
    }

    public function cambiarPasswordConToken(string $email, string $token, string $nuevaPassword): void 
    {
        if (!$this->repository->verificarTokenValido($email, $token)) {
            throw new Exception("El código es inválido o ha expirado.");
        }

        $hash = password_hash($nuevaPassword, PASSWORD_BCRYPT);
        $this->repository->actualizarPasswordYLimpiarToken($email, $hash);
    }
}