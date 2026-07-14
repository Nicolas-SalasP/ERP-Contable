<?php

namespace App\Domains\Contabilidad\Models;

use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;

// Hoy todas las queries reales pasan por DB::table('mapeo_cuentas_sii') (ver ImpuestosService,
// PlanCuentaService), ya con empresa_id filtrado a mano en cada una — este modelo Eloquent no
// se usa en ningun query real. El scope se agrega igual como defense-in-depth por si en el
// futuro alguien empieza a usarlo directamente.
class MapeoCuentaSii extends Model
{
    use HasEmpresaScope;
}
