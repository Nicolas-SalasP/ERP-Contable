<?php

namespace App\Domains\Integraciones\Controllers\V1;

use App\Domains\Integraciones\Models\IntegracionApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PingController
{
    public function index(Request $request): JsonResponse
    {
        /** @var IntegracionApiKey $key */
        $key = $request->attributes->get('integracion_key');

        return response()->json([
            'success' => true,
            'data' => ['empresa_id' => $key->empresa_id],
        ]);
    }
}
