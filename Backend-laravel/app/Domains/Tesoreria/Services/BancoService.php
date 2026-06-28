<?php

namespace App\Domains\Tesoreria\Services;

use App\Domains\Tesoreria\Exceptions\TesoreriaException;

use App\Domains\Tesoreria\Models\CatalogoBanco;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Support\Facades\DB;

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
        // numero_cuenta está cifrado con IV aleatorio; no se puede comparar con WHERE.
        // Se carga el subconjunto empresa+banco y se compara el valor descifrado en PHP.
        $existe = CuentaBancariaEmpresa::where('empresa_id', $datos['empresa_id'])
            ->where('banco', $datos['banco'])
            ->get()
            ->contains(fn ($c) => $c->numero_cuenta === $datos['numero_cuenta']);

        if ($existe) {
            throw TesoreriaException::regla("Esta cuenta bancaria ya se encuentra registrada para su empresa.");
        }

        return CuentaBancariaEmpresa::create($datos);
    }

    public function pagarNominaMasiva($empresaId, $usuarioId, $facturasIds, $cuentaBancariaId)
    {
        return DB::transaction(function () use ($empresaId, $usuarioId, $facturasIds, $cuentaBancariaId) {

            // SEGURIDAD: validar que la cuenta bancaria pertenezca a la empresa del usuario.
            $cuentaPertenece = CuentaBancariaEmpresa::where('id', $cuentaBancariaId)
                ->where('empresa_id', $empresaId)
                ->exists();

            if (!$cuentaPertenece) {
                throw TesoreriaException::noEncontrado("Cuenta bancaria no pertenece a la empresa.");
            }

            // Lock pesimista sobre las facturas solicitadas para evitar doble pago concurrente.
            $facturas = Factura::where('empresa_id', $empresaId)
                ->whereIn('id', $facturasIds)
                ->lockForUpdate()
                ->get();

            if ($facturas->isEmpty()) {
                throw TesoreriaException::noEncontrado("No se encontraron facturas para la empresa.");
            }

            // Detectar facturas ya pagadas/anuladas antes de modificar cualquiera.
            $noDisponibles = $facturas->whereIn('estado', ['PAGADA', 'ANULADA']);
            if ($noDisponibles->isNotEmpty()) {
                $folios = $noDisponibles->pluck('numero_factura')->implode(', ');
                throw TesoreriaException::regla("No se puede procesar: las siguientes facturas ya están PAGADAS o ANULADAS: {$folios}.");
            }

            // Verificar que todos los IDs solicitados existen en la empresa.
            $idsEncontrados = $facturas->pluck('id')->all();
            $idsNoEncontrados = array_diff($facturasIds, $idsEncontrados);
            if (!empty($idsNoEncontrados)) {
                throw TesoreriaException::noEncontrado("No se encontraron facturas pendientes (IDs no hallados: " . implode(', ', $idsNoEncontrados) . ").");
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

            // Usa la cuenta contable real del banco; lanza excepción si no está configurada.
            $cuentaContableBanco = $this->obtenerCuentaContableDeBanco($empresaId, $cuentaBancariaId);

            $datosAsiento = [
                'empresa_id'     => $empresaId,
                'usuario_id'     => $usuarioId,
                'fecha'          => $fechaHoy,
                'glosa'          => "Pago masivo nómina facturas: " . implode(', ', $numerosFacturas),
                'tipo_asiento'   => 'egreso',
                'origen_modulo'  => 'tesoreria',
                'detalles'       => [
                    [
                        'cuenta_contable' => '352105',
                        'debe'            => $totalNomina,
                        'haber'           => 0,
                        'tipo_operacion'  => 'DEBE'
                    ],
                    [
                        'cuenta_contable' => $cuentaContableBanco,
                        'debe'            => 0,
                        'haber'           => $totalNomina,
                        'tipo_operacion'  => 'HABER'
                    ]
                ]
            ];

            $asiento = $this->asientoService->registrarAsiento($datosAsiento, $datosAsiento['detalles']);

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
            throw TesoreriaException::noEncontrado("Cuenta bancaria no encontrada o no pertenece a tu empresa.");
        }

        $tipoMov = $datos['tipo_movimiento'] ?? '';
        $esEntrada = in_array($tipoMov, ['INGRESO', 'ABONO']);
        $esSalida = in_array($tipoMov, ['EGRESO', 'CARGO']);
        $cargo = $esSalida ? $datos['monto'] : 0;
        $abono = $esEntrada ? $datos['monto'] : 0;

        $movimientoId = DB::table('movimientos_bancarios')->insertGetId([
            'empresa_id' => $empresaId,
            'cuenta_bancaria_id' => $datos['cuenta_bancaria_id'],
            'fecha' => $datos['fecha'],
            'hora' => now()->format('H:i:s'),
            'descripcion' => $datos['descripcion'],
            'nro_documento' => $datos['nro_documento'] ?? null,
            'cargo' => $cargo,
            'abono' => $abono,
            'estado' => 'PENDIENTE',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return [
            'id' => $movimientoId,
            'estado' => 'REGISTRADO', 
            'mensaje' => 'Movimiento guardado y listo para conciliar.',
            'datos_ingresados' => $datos
        ];
    }

    public function procesarCartola(int $empresaId, int $usuarioId, int $cuentaBancariaId, string $cuentaContrapartida, $archivo): array
    {
        // Precondición FUERA de la transacción: valida pertenencia de la cuenta y
        // que tenga cuenta contable configurada. Sus errores (no encontrada / sin
        // configuración) deben propagarse tal cual, no envolverse como error de archivo.
        $codigoCuentaBanco = $this->obtenerCuentaContableDeBanco($empresaId, $cuentaBancariaId);

        try {
            // DB::transaction garantiza el rollback ante cualquier excepción (antes se
            // usaba beginTransaction()/commit() con un catch que no capturaba las
            // TesoreriaException por falta del import, dejando la transacción abierta).
            return DB::transaction(function () use (
                $empresaId, $usuarioId, $cuentaBancariaId, $cuentaContrapartida, $codigoCuentaBanco, $archivo
            ): array {
                $gestor = fopen($archivo->getRealPath(), "r");
                $importados = 0;
                $ignorados = 0;

                try {
                    $esCabecera = true;

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
                } finally {
                    if (is_resource($gestor)) {
                        fclose($gestor);
                    }
                }

                return [
                    'importados' => $importados,
                    'ignorados' => $ignorados
                ];
            });
        } catch (TesoreriaException $e) {
            // Error de dominio ya claro; no se envuelve.
            throw $e;
        } catch (\Throwable $e) {
            // El rollback ya ocurrió dentro de DB::transaction. Aquí solo llegan
            // errores de procesamiento del archivo, que se reportan como tales.
            throw TesoreriaException::regla("El archivo contiene errores y la importación fue abortada. Error: " . $e->getMessage());
        }
    }

    public function obtenerCuentaBancaria(int $empresaId, int $id)
    {
        $cuenta = CuentaBancariaEmpresa::where('empresa_id', $empresaId)->find($id);
        if (!$cuenta) throw TesoreriaException::noEncontrado("Cuenta bancaria no encontrada.");
        return $cuenta;
    }

    public function obtenerCuentaContableDeBanco(int $empresaId, int $id)
    {
        $cuenta = $this->obtenerCuentaBancaria($empresaId, $id);
        
        if (empty($cuenta->cuenta_contable)) {
            throw TesoreriaException::regla("La cuenta bancaria '{$cuenta->banco}' no tiene un código contable asignado en su configuración. Por favor, edite la cuenta y asígnele una.");
        }
        
        return $cuenta->cuenta_contable;
    }

    public function obtenerMovimiento(int $empresaId, int $id)
    {
        $mov = DB::table('movimientos_bancarios')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();
            
        if (!$mov) throw TesoreriaException::noEncontrado("Movimiento bancario no encontrado.");
        return $mov;
    }

    /**
     * Obtiene un movimiento para conciliarlo, bloqueandolo (lockForUpdate) y
     * rechazandolo si ya fue conciliado. DEBE llamarse dentro de una transaccion.
     *
     * Evita la doble conciliacion: sin el lock + guard de estado, un doble click
     * o reintento podia reprocesar un movimiento CONCILIADO y generar un segundo
     * asiento de banco (doble cargo/abono) y re-pagar facturas.
     */
    public function obtenerMovimientoParaConciliar(int $empresaId, int $id)
    {
        $mov = DB::table('movimientos_bancarios')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->lockForUpdate()
            ->first();

        if (!$mov) throw TesoreriaException::noEncontrado("Movimiento bancario no encontrado.");

        if (isset($mov->estado) && $mov->estado === 'CONCILIADO') {
            throw TesoreriaException::regla("El movimiento bancario ya fue conciliado.");
        }

        return $mov;
    }

    public function vincularAsientoAMovimiento(int $empresaId, int $movimientoId, int $asientoId)
    {
        DB::table('movimientos_bancarios')
            ->where('empresa_id', $empresaId)
            ->where('id', $movimientoId)
            ->update([
                'estado' => 'CONCILIADO',
                'asiento_id' => $asientoId
            ]);
    }

    public function obtenerMovimientosPendientes(int $empresaId, int $cuentaBancariaId)
    {
        $this->obtenerCuentaBancaria($empresaId, $cuentaBancariaId);
        return DB::table('movimientos_bancarios')
            ->where('empresa_id', $empresaId)
            ->where('cuenta_bancaria_id', $cuentaBancariaId)
            ->where('estado', 'PENDIENTE')
            ->orderBy('fecha', 'asc')
            ->get();
    }

    public function obtenerAnticiposPendientes(int $empresaId)
    {
        return DB::table('anticipos_proveedores')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'PENDIENTE')
            ->get();
    }

    public function vincularMovimientoAAnticipo(int $empresaId, int $movimientoId, int $anticipoId)
    {
        DB::transaction(function () use ($empresaId, $movimientoId, $anticipoId) {
            // Lock pesimista + guard de estado para evitar doble conciliación en carrera.
            $movimiento = $this->obtenerMovimientoParaConciliar($empresaId, $movimientoId);

            // obtenerMovimientoParaConciliar ya rechaza CONCILIADO; validamos también el estado anticipo.
            $anticipo = DB::table('anticipos_proveedores')
                ->where('empresa_id', $empresaId)
                ->where('id', $anticipoId)
                ->lockForUpdate()
                ->first();

            if (!$anticipo) {
                throw TesoreriaException::noEncontrado("El anticipo no existe o no pertenece a tu empresa.");
            }

            if ($anticipo->estado !== 'PENDIENTE') {
                throw TesoreriaException::regla("El anticipo #{$anticipoId} ya fue vinculado o no está en estado PENDIENTE (estado actual: {$anticipo->estado}).");
            }

            DB::table('movimientos_bancarios')
                ->where('empresa_id', $empresaId)
                ->where('id', $movimientoId)
                ->update(['estado' => 'CONCILIADO_ANTICIPO']);

            DB::table('anticipos_proveedores')
                ->where('empresa_id', $empresaId)
                ->where('id', $anticipoId)
                ->update(['estado' => 'PAGADO', 'movimiento_id' => $movimientoId]);
        });
    }

    public function obtenerMovimientosPorCuenta(int $empresaId, int $cuentaBancariaId)
    {
        $this->obtenerCuentaBancaria($empresaId, $cuentaBancariaId);

        return DB::table('movimientos_bancarios')
            ->where('empresa_id', $empresaId)
            ->where('cuenta_bancaria_id', $cuentaBancariaId)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }
}