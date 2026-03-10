<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AutenticacionRepository;
use App\Helpers\JwtHelper;
use App\Config\Env;
use Exception;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

class AutenticacionService {

    private AutenticacionRepository $repository;

    public function __construct() {
        $this->repository = new AutenticacionRepository();
    }

    // =========================================================================
    // 1. INICIAR SESIÓN
    // =========================================================================
    public function iniciarSesion(string $email, string $password): array {
        
        $usuario = $this->repository->buscarUsuarioPorEmail($email);

        if (!$usuario) {
            $this->registrarAuditoria('LOGIN_FALLIDO', null, ['email_intentado' => $email]);
            throw new Exception('CREDENCIALES_INCORRECTAS');
        }

        if (isset($usuario['bloqueado_hasta']) && $usuario['bloqueado_hasta'] !== null && strtotime($usuario['bloqueado_hasta']) > time()) {
            $minutosRestantes = ceil((strtotime($usuario['bloqueado_hasta']) - time()) / 60);
            
            if ($minutosRestantes > 60) {
                $horas = ceil($minutosRestantes / 60);
                throw new Exception("Por seguridad, tu cuenta está bloqueada. Intenta de nuevo en {$horas} horas.");
            }
            
            throw new Exception("Por seguridad, tu cuenta está bloqueada. Intenta de nuevo en {$minutosRestantes} minutos.");
        }

        if (!password_verify($password, $usuario['password'])) {
            
            $intentos = (int)($usuario['intentos_fallidos'] ?? 0) + 1;
            $nivel = (int)($usuario['nivel_bloqueo'] ?? 0);
            $bloqueadoHasta = null;

            if ($intentos >= 5) {
                $nivel++; 
                
                if ($nivel === 1) $minutosCastigo = 15;
                elseif ($nivel === 2) $minutosCastigo = 30;
                elseif ($nivel === 3) $minutosCastigo = 60;
                else $minutosCastigo = 1440;

                $bloqueadoHasta = date('Y-m-d H:i:s', strtotime("+$minutosCastigo minutes"));
                
                $this->repository->registrarIntentoFallido((int)$usuario['id'], $intentos, $nivel, $bloqueadoHasta);
                $this->registrarAuditoria('BLOQUEO_CUENTA', (int)$usuario['id'], ['nivel_bloqueo' => $nivel, 'minutos' => $minutosCastigo]);

                throw new Exception("Demasiados intentos fallidos. Tu cuenta ha sido bloqueada por {$minutosCastigo} minutos.");
            } else {
                $this->repository->registrarIntentoFallido((int)$usuario['id'], $intentos, $nivel, null);
                
                $restantes = 5 - $intentos;
                $this->registrarAuditoria('LOGIN_FALLIDO', (int)$usuario['id'], ['email_intentado' => $email]);
                
                throw new Exception("Credenciales incorrectas. Te quedan {$restantes} intentos antes de bloquear la cuenta.");
            }
        }

        if ((isset($usuario['intentos_fallidos']) && $usuario['intentos_fallidos'] > 0) || (isset($usuario['bloqueado_hasta']) && $usuario['bloqueado_hasta'] !== null)) {
            $this->repository->limpiarIntentosFallidos((int)$usuario['id']);
        }

        if ((int)$usuario['estado_suscripcion_id'] !== 1) {
            throw new Exception('CUENTA_SUSPENDIDA');
        }

        if ($usuario['fecha_fin_suscripcion'] && $usuario['fecha_fin_suscripcion'] < date('Y-m-d')) {
            throw new Exception('PLAN_VENCIDO');
        }

        $nuevaVersion = $this->repository->rotarVersionToken((int)$usuario['id']);

        $token = JwtHelper::generate([
            'id' => $usuario['id'],
            'rol' => $usuario['rol_id'],
            'empresa_id' => $usuario['empresa_id'],
            'nombre' => $usuario['nombre'],
            'version_token' => $nuevaVersion
        ]);

        $this->registrarAuditoria('LOGIN_EXITOSO', (int)$usuario['id'], ['empresa_id' => $usuario['empresa_id']]);
        
        unset($usuario['password']);

        return [
            'token' => $token,
            'usuario' => $usuario
        ];
    }

    private function registrarAuditoria(string $accion, ?int $usuarioId, array $detalles = []): void {
        if (class_exists('App\Services\AuditoriaService')) {
            try {
                \App\Services\AuditoriaService::registrar($accion, 'usuarios', $usuarioId, null, $detalles);
            } catch (Exception $e) {
                error_log("Fallo al registrar auditoría: " . $e->getMessage());
            }
        }
    }


    // =========================================================================
    // 2. RECUPERACIÓN DE CONTRASEÑA
    // =========================================================================

    public function iniciarRecuperacion(string $email): void 
    {
        $usuario = $this->repository->buscarUsuarioPorEmail($email);
        
        if (!$usuario) {
            return; 
        }

        $codigo = (string)rand(100000, 999999);
        $this->repository->guardarTokenRecuperacion($email, $codigo);
        $this->enviarCorreoRecuperacion($email, $codigo);
    }

    private function enviarCorreoRecuperacion(string $destinatario, string $codigo): void 
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = Env::get('SMTP_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = Env::get('SMTP_USER');
            $mail->Password   = Env::get('SMTP_PASS');
            
            if (Env::get('SMTP_SECURE') === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            }
            
            $mail->Port       = (int) Env::get('SMTP_PORT');

            $mail->setFrom(Env::get('SMTP_FROM_EMAIL'), Env::get('SMTP_FROM_NAME'));
            $mail->addAddress($destinatario);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Código de Recuperación de Contraseña';
            
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
                    <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 8px; border-top: 4px solid #059669;'>
                        <h2 style='color: #1f2937; text-align: center;'>Recuperación de Acceso</h2>
                        <p style='color: #4b5563;'>Has solicitado restablecer tu contraseña. Utiliza el siguiente código:</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <span style='font-size: 32px; font-weight: bold; letter-spacing: 8px; background-color: #ecfdf5; color: #047857; padding: 15px 30px; border-radius: 8px;'>
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
            throw new Exception("Error al enviar el correo");
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