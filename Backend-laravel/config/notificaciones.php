<?php

/**
 * Notificaciones por correo de cotizaciones/facturas (PDF adjunto + auditoria).
 *
 * cliente_habilitado: kill-switch global, deliberadamente apagado por defecto.
 * La logica de enviar tambien al cliente (contacto_email de la cotizacion/factura,
 * nunca un email libre del request) ya esta armada en los Jobs, pero no se ejecuta
 * hasta que esto se active explicitamente -- evita mandar correos a clientes reales
 * antes de validar plantillas/contenido con el negocio.
 */
return [
    'cliente_habilitado' => env('NOTIFICACIONES_CLIENTE_HABILITADO', false),
];
