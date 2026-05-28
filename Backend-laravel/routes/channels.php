<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('inventario.empresa.{empresaId}', function ($user, int $empresaId) {
    return (int) $user->empresa_id === $empresaId;
});
