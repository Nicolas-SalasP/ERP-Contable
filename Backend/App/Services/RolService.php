<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\RolRepository;
use App\Helpers\MailHelper;
use Exception;

class RolService
{
    private RolRepository $repository;

    public function __construct()
    {
        $this->repository = new RolRepository();
    }

    public function obtenerRoles()
    {
        return $this->repository->listarRoles();
    }

    public function crear(array $data)
    {
        if (empty($data['nombre']))
            throw new Exception("El nombre del rol es obligatorio.");
        $rolId = $this->repository->crearRol($data['nombre'], $data['descripcion'] ?? '');

        if (isset($data['permisos']) && is_array($data['permisos'])) {
            $this->repository->sincronizarPermisosDeRol($rolId, $data['permisos']);
        }

        return ['success' => true, 'mensaje' => 'Rol creado con éxito.'];
    }

    public function actualizar(int $id, array $data)
    {
        if (empty($data['nombre']))
            throw new Exception("El nombre del rol es obligatorio.");
        $exito = $this->repository->actualizarRol($id, $data['nombre'], $data['descripcion'] ?? '');
        if (!$exito)
            throw new Exception("No se pudo actualizar. Es un rol de sistema o no existe.");

        if (isset($data['permisos']) && is_array($data['permisos'])) {
            $this->repository->sincronizarPermisosDeRol($id, $data['permisos']);
        }

        return ['success' => true, 'mensaje' => 'Rol actualizado.'];
    }

    public function eliminar(int $id)
    {
        $exito = $this->repository->eliminarRol($id);
        if (!$exito)
            throw new Exception("No se pudo eliminar. Es un rol de sistema o no tienes permiso.");
        return ['success' => true, 'mensaje' => 'Rol eliminado.'];
    }

    public function listarPermisos()
    {
        return $this->repository->listarTodosLosPermisos();
    }

    public function obtenerPermisosDeRol(int $rolId)
    {
        return $this->repository->obtenerPermisosDeRol($rolId);
    }

    public function invitarUsuario(array $data)
    {
        if (empty($data['email']) || empty($data['rol_id'])) {
            throw new Exception("Datos incompletos.");
        }

        // Lógica para registrar al usuario o vincularlo a la empresa en la BD...
        // $this->repository->registrarInvitacion($data);

        $linkAcceso = "https://erp.atlasdigitaltech.cl/login";
        
        $html = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8fafc; padding: 40px 20px; border-radius: 12px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h2 style='color: #0f172a; margin: 0; font-size: 24px; letter-spacing: 2px;'>ERP<span style='color: #10b981;'>CONTABLE</span></h2>
            </div>
            <div style='background-color: #ffffff; padding: 40px 30px; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                <h3 style='color: #1e293b; margin-top: 0; font-size: 20px;'>¡Te damos la bienvenida al equipo!</h3>
                <p style='color: #475569; line-height: 1.6; font-size: 15px;'>Has sido invitado para unirte y colaborar en nuestro sistema de gestión ERP Contable.</p>
                <p style='color: #475569; line-height: 1.6; font-size: 15px;'>Para aceptar la invitación y comenzar a trabajar, haz clic en el siguiente botón e inicia sesión (o regístrate si aún no tienes cuenta en AtlasWeb):</p>
                <div style='text-align: center; margin: 35px 0;'>
                    <a href='{$linkAcceso}' style='background-color: #10b981; color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 8px; font-weight: bold; font-size: 15px; display: inline-block;'>Acceder al Sistema</a>
                </div>
                <p style='color: #94a3b8; font-size: 12px; text-align: center; margin-bottom: 0; border-top: 1px solid #f1f5f9; padding-top: 20px;'>Si el botón no funciona, copia y pega este enlace en tu navegador:<br><a href='{$linkAcceso}' style='color: #10b981; text-decoration: none;'>{$linkAcceso}</a></p>
            </div>
        </div>";

       MailHelper::enviar('bienvenida', $data['email'], 'Invitación al ERP Contable', $html);

        return ['success' => true, 'mensaje' => 'Invitación enviada.'];
    }
}