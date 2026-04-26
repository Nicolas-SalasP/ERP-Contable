<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Domains\Comercial\Models{
/**
 * @property-read \App\Domains\Core\Models\Empresa|null $empresa
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente query()
 */
	class Cliente extends \Eloquent {}
}

namespace App\Domains\Comercial\Models{
/**
 * @property-read \App\Domains\Comercial\Models\Cliente|null $cliente
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domains\Comercial\Models\CotizacionDetalle> $detalles
 * @property-read int|null $detalles_count
 * @property-read \App\Domains\Core\Models\Empresa|null $empresa
 * @property-read \App\Domains\Comercial\Models\EstadoCotizacion|null $estado
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion query()
 */
	class Cotizacion extends \Eloquent {}
}

namespace App\Domains\Comercial\Models{
/**
 * @property-read \App\Domains\Comercial\Models\Cotizacion|null $cotizacion
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionDetalle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionDetalle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionDetalle query()
 */
	class CotizacionDetalle extends \Eloquent {}
}

namespace App\Domains\Comercial\Models{
/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domains\Comercial\Models\Cotizacion> $cotizaciones
 * @property-read int|null $cotizaciones_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstadoCotizacion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstadoCotizacion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstadoCotizacion query()
 */
	class EstadoCotizacion extends \Eloquent {}
}

namespace App\Domains\Comercial\Models{
/**
 * @property-read \App\Domains\Tesoreria\Models\CuentaBancariaProveedor|null $cuentaBancaria
 * @property-read \App\Domains\Core\Models\Empresa|null $empresa
 * @property-read \App\Domains\Comercial\Models\Proveedor|null $proveedor
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura query()
 */
	class Factura extends \Eloquent {}
}

namespace App\Domains\Comercial\Models{
/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domains\Tesoreria\Models\CuentaBancariaProveedor> $cuentasBancarias
 * @property-read int|null $cuentas_bancarias_count
 * @property-read \App\Domains\Core\Models\Empresa|null $empresa
 * @property-read \App\Domains\Core\Models\Pais|null $pais
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor query()
 */
	class Proveedor extends \Eloquent {}
}

namespace App\Domains\Contabilidad\Models{
/**
 * @property-read \App\Domains\Contabilidad\Models\CentroCosto|null $centroCosto
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domains\Contabilidad\Models\DetalleAsiento> $detalles
 * @property-read int|null $detalles_count
 * @property-read \App\Domains\Core\Models\Empresa|null $empresa
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AsientoContable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AsientoContable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AsientoContable query()
 */
	class AsientoContable extends \Eloquent {}
}

namespace App\Domains\Contabilidad\Models{
/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CatalogoPlanMaestro newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CatalogoPlanMaestro newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CatalogoPlanMaestro query()
 */
	class CatalogoPlanMaestro extends \Eloquent {}
}

namespace App\Domains\Contabilidad\Models{
/**
 * @property-read \App\Domains\Core\Models\Empresa|null $empresa
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentroCosto newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentroCosto newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentroCosto query()
 */
	class CentroCosto extends \Eloquent {}
}

namespace App\Domains\Contabilidad\Models{
/**
 * @property-read \App\Domains\Contabilidad\Models\AsientoContable|null $asiento
 * @property-read \App\Domains\Contabilidad\Models\PlanCuenta|null $cuenta
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DetalleAsiento newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DetalleAsiento newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DetalleAsiento query()
 */
	class DetalleAsiento extends \Eloquent {}
}

namespace App\Domains\Contabilidad\Models{
/**
 * @property-read \App\Domains\Core\Models\Empresa|null $empresa
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanCuenta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanCuenta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanCuenta query()
 */
	class PlanCuenta extends \Eloquent {}
}

namespace App\Domains\Contabilidad\Models{
/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiiCategoriaActivo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiiCategoriaActivo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiiCategoriaActivo query()
 */
	class SiiCategoriaActivo extends \Eloquent {}
}

namespace App\Domains\Core\Models{
/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domains\Core\Models\User> $usuarios
 * @property-read int|null $usuarios_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa query()
 */
	class Empresa extends \Eloquent {}
}

namespace App\Domains\Core\Models{
/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domains\Core\Models\User> $usuarios
 * @property-read int|null $usuarios_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstadoSuscripcion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstadoSuscripcion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EstadoSuscripcion query()
 */
	class EstadoSuscripcion extends \Eloquent {}
}

namespace App\Domains\Core\Models{
/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pais newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pais newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pais query()
 */
	class Pais extends \Eloquent {}
}

namespace App\Domains\Core\Models{
/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domains\Core\Models\User> $usuarios
 * @property-read int|null $usuarios_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rol newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rol newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rol query()
 */
	class Rol extends \Eloquent {}
}

namespace App\Domains\Core\Models{
/**
 * @property-read \App\Domains\Core\Models\Empresa|null $empresa
 * @property-read \App\Domains\Core\Models\EstadoSuscripcion|null $estadoSuscripcion
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Domains\Core\Models\Rol|null $rol
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 */
	class User extends \Eloquent {}
}

namespace App\Domains\Tesoreria\Models{
/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CatalogoBanco newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CatalogoBanco newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CatalogoBanco query()
 */
	class CatalogoBanco extends \Eloquent {}
}

namespace App\Domains\Tesoreria\Models{
/**
 * @property-read \App\Domains\Core\Models\Empresa|null $empresa
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CuentaBancariaEmpresa newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CuentaBancariaEmpresa newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CuentaBancariaEmpresa query()
 */
	class CuentaBancariaEmpresa extends \Eloquent {}
}

namespace App\Domains\Tesoreria\Models{
/**
 * @property-read \App\Domains\Core\Models\Pais|null $pais
 * @property-read \App\Domains\Comercial\Models\Proveedor|null $proveedor
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CuentaBancariaProveedor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CuentaBancariaProveedor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CuentaBancariaProveedor query()
 */
	class CuentaBancariaProveedor extends \Eloquent {}
}

