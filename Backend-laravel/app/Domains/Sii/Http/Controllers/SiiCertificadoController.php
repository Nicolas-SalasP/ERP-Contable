<?php

namespace App\Domains\Sii\Http\Controllers;

use App\Domains\Sii\Exceptions\CertificadoInvalidoException;
use App\Domains\Sii\Http\Requests\SubirCertificadoRequest;
use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use App\Domains\Sii\Services\Certificado\CertificadoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SiiCertificadoController extends Controller
{
    public function __construct(private readonly CertificadoService $certificados)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $cert = $this->certificadoActivoDe($request->user()->empresa_id);

        if ($cert === null) {
            return response()->json([
                'mensaje' => 'No hay certificado activo para esta empresa. Suba uno con POST /api/sii/certificado.',
            ], 404);
        }

        return response()->json($cert);
    }

    public function store(SubirCertificadoRequest $request): JsonResponse
    {
        $archivo  = $request->file('archivo');
        $contenido = file_get_contents($archivo->getRealPath());
        $password  = (string) $request->input('password');

        try {
            $cert = $this->certificados->cargar(
                $request->user()->empresa_id,
                $contenido,
                $password
            );
        } catch (CertificadoInvalidoException $e) {
            return response()->json([
                'mensaje' => $e->getMessage(),
                'motivo'  => $e->motivo,
            ], 422);
        }

        return response()->json($cert, 201);
    }

    public function destroy(Request $request, int $id): Response
    {
        $cert = SiiCertificadoEmpresa::query()
            ->where('id', $id)
            ->where('empresa_id', $request->user()->empresa_id)
            ->firstOrFail();

        $this->certificados->revocar($cert);

        return response()->noContent();
    }

    public function verificar(Request $request): JsonResponse
    {
        $cert = $this->certificadoActivoDe($request->user()->empresa_id);

        if ($cert === null) {
            return response()->json([
                'mensaje' => 'No hay certificado activo para verificar.',
            ], 404);
        }

        $ok = $this->certificados->verificarIntegridad($cert);

        return response()->json([
            'integridad_ok' => $ok,
            'mensaje'       => $ok
                ? 'El certificado se descifra correctamente con la APP_KEY actual.'
                : 'No se pudo descifrar/leer el certificado con la APP_KEY actual. Revisar logs canal sii.',
        ]);
    }

    private function certificadoActivoDe(int $empresaId): ?SiiCertificadoEmpresa
    {
        return SiiCertificadoEmpresa::query()
            ->activos()
            ->porEmpresa($empresaId)
            ->first();
    }
}
