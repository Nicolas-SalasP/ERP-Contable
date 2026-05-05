<?php

namespace App\Domains\Tesoreria\Services;

use App\Domains\Tesoreria\Models\CatalogoBanco;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Support\Facades\DB;
use Exception;

class BancoService
{
    protected $asientoService;

    public function __construct(AsientoContableService $asientoService)
    {
        $this->asientoService = $asientoService;
    }

    public function obtenerCatalogo()
    {
        return CatalogoBanco::orderBy('nombre')->get();
    }

    public function obtenerCuentasPorEmpresa(int $empresaId)
    {
        return CuentaBancariaEmpresa::where('empresa_id', $empresaId)->get();
    }

    public function registrarCuentaPropia(array $datos): CuentaBancariaEmpresa
    {
        $existe = CuentaBancariaEmpresa::where('empresa_id', $datos['empresa_id'])
            ->where('banco', $datos['banco'])
            ->where('numero_cuenta', $datos['numero_cuenta'])
            ->exists();

        if ($existe) {
            throw new Exception("Esta cuenta bancaria ya se encuentra registrada para su empresa.");
        }

        return CuentaBancariaEmpresa::create($datos);
    }

    public function pagarNominaMasiva($empresaId, $usuarioId, $facturasIds, $cuentaBancariaId)
    {
        return DB::transaction(function () use ($empresaId, $usuarioId, $facturasIds, $cuentaBancariaId) {
            
            $facturas = Factura::where('empresa_id', $empresaId)
                ->whereIn('id', $facturasIds)
                ->get();

            if ($facturas->isEmpty()) {
                throw new Exception("No se encontraron facturas pendientes.");
            }

            $totalNomina = 0;
            $numerosFacturas = [];
            $fechaHoy = now()->format('Y-m-d');

            foreach ($facturas as $factura) {
                /** @var Factura $factura */
                $factura->estado = 'PAGADA'; 
                $factura->save();

                $totalNomina += $factura->monto_bruto;
                $numerosFacturas[] = $factura->numero_factura;
            }

            $cuentaEmpresa = CuentaBancariaEmpresa::find($cuentaBancariaId);
            $cuentaContableBanco = $cuentaEmpresa->cuenta_contable_id ?? 110201; 

            $datosAsiento = [
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'fecha'      => $fechaHoy,
                'glosa'      => "Pago masivo nómina facturas: " . implode(', ', $numerosFacturas),
                'tipo'       => 'EGRESO',
                'detalles'   => [
                    [
                        'cuenta_contable' => '352105',
                        'debe'            => $totalNomina,
                        'haber'           => 0,
                        'fecha'           => $fechaHoy,
                        'tipo_operacion'  => 'DEBE'
                    ],
                    [
                        'cuenta_contable' => $cuentaContableBanco,
                        'debe'            => 0,
                        'haber'           => $totalNomina,
                        'fecha'           => $fechaHoy,
                        'tipo_operacion'  => 'HABER'
                    ]
                ]
            ];

            $asiento = $this->asientoService->crearAsientoManual($datosAsiento);

            return [
                'success'    => true,
                'mensaje'    => 'Nómina pagada y facturas actualizadas correctamente.',
                'asiento_id' => $asiento->numero_comprobante,
                'total'      => $totalNomina
            ];
        });
    }

    public function registrarIngresoManual(int $empresaId, array $datos): array
    {
        $cuenta = CuentaBancariaEmpresa::where('empresa_id', $empresaId)->find($datos['cuenta_bancaria_id']);
        
        if (!$cuenta) {
            throw new Exception("Cuenta bancaria no encontrada o no pertenece a tu empresa.", 403); // <--- Lanza 403
        }

        return [
            'estado' => 'REGISTRADO', 
            'mensaje' => 'Movimiento guardado exitosamente.',
            'datos_ingresados' => $datos
        ];
    }

    public function procesarCartola(int $empresaId, int $usuarioId, int $cuentaBancariaId, string $cuentaContrapartida, $archivo): array
    {
        DB::beginTransaction();
        
        try {
            $cuentaBanco = CuentaBancariaEmpresa::where('empresa_id', $empresaId)->findOrFail($cuentaBancariaId);
            $codigoCuentaBanco = $cuentaBanco->cuenta_contable_codigo ?? '1-1-01-01';

            $gestor = fopen($archivo->getRealPath(), "r");
            $esCabecera = true;
            $importados = 0;
            $ignorados = 0;

            while (($fila = fgetcsv($gestor, 1000, ",")) !== FALSE) {
                if ($esCabecera) {
                    $esCabecera = false;
                    continue;
                }

                if (count($fila) < 3) continue; 

                $fecha = date('Y-m-d', strtotime(str_replace('/', '-', $fila[0])));
                $descripcion = substr(trim($fila[1]), 0, 255);
                $monto = (float) $fila[2];

                if ($monto == 0) continue;

                $existeDuplicado = $this->asientoService->existeAsientoPorOrigen(
                    $empresaId,
                    'importacion_banco',
                    $cuentaBancariaId,
                    $fecha,
                    $descripcion
                );

                if ($existeDuplicado) {
                    $ignorados++;
                    continue;
                }

                $detalles = [];
                $montoAbsoluto = abs($monto);

                if ($monto > 0) {
                    $detalles[] = ['cuenta_contable' => $codigoCuentaBanco, 'debe' => $montoAbsoluto, 'haber' => 0];
                    $detalles[] = ['cuenta_contable' => $cuentaContrapartida, 'debe' => 0, 'haber' => $montoAbsoluto];
                } else {
                    $detalles[] = ['cuenta_contable' => $cuentaContrapartida, 'debe' => $montoAbsoluto, 'haber' => 0];
                    $detalles[] = ['cuenta_contable' => $codigoCuentaBanco, 'debe' => 0, 'haber' => $montoAbsoluto];
                }

                $cabeceraAsiento = [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'fecha' => $fecha,
                    'glosa' => $descripcion,
                    'tipo_asiento' => 'traspaso',
                    'origen_modulo' => 'importacion_banco',
                    'origen_id' => $cuentaBancariaId,
                    'estado' => 'MAYORIZADO'
                ];

                $this->asientoService->registrarAsiento($cabeceraAsiento, $detalles);
                $importados++;
            }
            fclose($gestor);

            DB::commit();

            return [
                'importados' => $importados,
                'ignorados' => $ignorados
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("El archivo contiene errores y la importación fue abortada. Error: " . $e->getMessage());
        }
    }
}