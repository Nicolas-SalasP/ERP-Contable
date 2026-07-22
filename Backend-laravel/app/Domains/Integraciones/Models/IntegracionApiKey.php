<?php

namespace App\Domains\Integraciones\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegracionApiKey extends Model
{
    use HasEmpresaScope;

    protected $table = 'integracion_api_keys';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'prefijo',
        'token_hash',
        'scopes',
        'expira_at',
        'revocada_at',
        'ultimo_uso_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'scopes' => 'array',
        'ultimo_uso_at' => 'datetime',
        'expira_at' => 'datetime',
        'revocada_at' => 'datetime',
    ];

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function estaRevocada(): bool
    {
        return $this->revocada_at !== null;
    }

    public function estaExpirada(): bool
    {
        return $this->expira_at !== null && $this->expira_at->isPast();
    }

    public function estaVigente(): bool
    {
        return ! $this->estaRevocada() && ! $this->estaExpirada();
    }

    public function tieneScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}
