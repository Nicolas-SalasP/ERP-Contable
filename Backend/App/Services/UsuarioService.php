<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UsuarioRepository;
use App\Helpers\MailHelper;
use Exception;

class UsuarioService
{
    private UsuarioRepository $repository;

    public function __construct()
    {
        $this->repository = new UsuarioRepository();
    }

    public function obtenerUsuarios(): array
    {
        return $this->repository->listarUsuariosEmpresa();
    }

    public function obtenerRolesDisponibles(): array
    {
        $roles = $this->repository->obtenerRoles();
        if (empty($roles)) {
            return [
                ['id' => 1, 'nombre' => 'Administrador'],
                ['id' => 2, 'nombre' => 'Finanzas / Contador'],
                ['id' => 3, 'nombre' => 'Ventas / Operador'],
                ['id' => 4, 'nombre' => 'Auditor (Solo Lectura)']
            ];
        }
        return $roles;
    }

    public function invitar(array $data): array
    {
        if (empty($data['email']) || empty($data['rol_id'])) {
            throw new Exception("El correo y el rol son obligatorios para invitar.");
        }

        $email = strtolower(trim($data['email']));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El formato del correo electrónico no es válido.");
        }
    
        $this->repository->invitarUsuario($email, (int) $data['rol_id']);

        $linkAcceso = "https://erp.atlasdigitaltech.cl/login";
        $html = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8fafc; padding: 40px 20px; border-radius: 12px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h2 style='color: #0f172a; margin: 0; font-size: 24px; letter-spacing: 2px;'>ERP<span style='color: #10b981;'>CONTABLE</span></h2>
            </div>
            <div style='background-color: #ffffff; padding: 40px 30px; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
                <h3 style='color: #1e293b; margin-top: 0; font-size: 20px;'>¡Te damos la bienvenida al equipo!</h3>
                <p style='color: #475569; line-height: 1.6; font-size: 15px;'>Has sido invitado para unirte y colaborar en nuestro sistema de gestión ERP Contable.</p>
                <p style='color: #475569; line-height: 1.6; font-size: 15px;'>Para aceptar la invitación y comenzar a trabajar, haz clic en el siguiente botón e inicia sesión con tu cuenta:</p>
                <div style='text-align: center; margin: 35px 0;'>
                    <a href='{$linkAcceso}' style='background-color: #10b981; color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 8px; font-weight: bold; font-size: 15px; display: inline-block;'>Acceder al Sistema</a>
                </div>
                <p style='color: #94a3b8; font-size: 12px; text-align: center; margin-bottom: 0; border-top: 1px solid #f1f5f9; padding-top: 20px;'>
                    Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                    <a href='{$linkAcceso}' style='color: #10b981; text-decoration: none;'>{$linkAcceso}</a>
                </p>
            </div>
        </div>";

        $resultadoCorreo = MailHelper::enviar('bienvenida', $email, 'Invitación al ERP Contable', $html);

        if (!$resultadoCorreo['success']) {
            throw new Exception("Usuario registrado en BD, pero falló el envío del correo: " . $resultadoCorreo['error']);
        }

        return ['success' => true, 'mensaje' => 'Usuario invitado correctamente y correo enviado.'];
    }

    public function actualizarRol(int $usuarioId, array $data): array
    {
        if (empty($data['rol_id'])) {
            throw new Exception("El nuevo rol es obligatorio.");
        }

        $exito = $this->repository->cambiarRolUsuario($usuarioId, (int) $data['rol_id']);
        if (!$exito) {
            throw new Exception("No se pudo actualizar el rol o el usuario no existe en tu empresa.");
        }

        return ['success' => true, 'mensaje' => 'Rol actualizado con éxito.'];
    }

    public function eliminarAcceso(int $usuarioId): array
    {
        $exito = $this->repository->desvincularUsuario($usuarioId);
        if (!$exito) {
            throw new Exception("No se pudo remover al usuario.");
        }

        return ['success' => true, 'mensaje' => 'Usuario desvinculado de la empresa.'];
    }
}