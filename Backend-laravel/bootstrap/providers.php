<?php

use App\Domains\Alertas\AlertasServiceProvider;
use App\Domains\Comercial\ComercialServiceProvider;
use App\Domains\Sii\SiiServiceProvider;
use App\Providers\AppServiceProvider;

return [
    SiiServiceProvider::class,
    AlertasServiceProvider::class,
    ComercialServiceProvider::class,
    AppServiceProvider::class,
];
