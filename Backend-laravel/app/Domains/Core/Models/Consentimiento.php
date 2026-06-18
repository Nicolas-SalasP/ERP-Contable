<?php

namespace App\Domains\Core\Models;

use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Consentimiento extends Model
{
    use HasEmpresaScope;

    protected $table = 'consentimientos';

    protected $fillable = [
        'empresa_id',
        'titular_type',
        'titular_id',
        'politica_version',
        'finalidad',
        'base_licitud',
        'otorgado',
        'ip',
        'user_agent',
        'otorgado_at',
        'revocado_at',
    ];

    protected function casts(): array
    {
        return [
            'otorgado'    => 'boolean',
            'otorgado_at' => 'datetime',
            'revocado_at' => 'datetime',
        ];
    }

    public function titular(): MorphTo
    {
        return $this->morphTo();
    }
}
