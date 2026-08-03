<?php

namespace App\Domains\Tesoreria\Services;

use App\Domains\Tesoreria\Exceptions\TesoreriaException;

use App\Domains\Tesoreria\Models\CatalogoBanco;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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
        // numero_cuenta está cifrado con IV aleatorio: se compara descifrado en PHP, no con WHERE.
        $existe = CuentaBancariaEmpresa::where('empresa_id', $datos['empresa_id'])
            ->where('banco', $datos['banco'])
            ->get()
            ->contains(fn ($c) => $c->numero_cuenta === $datos['numero_cuenta']);

        if ($existe) {
            throw TesoreriaException::regla("Esta cuenta bancaria ya se encuentra registrada para su empresa.");
        }

        return CuentaBancariaEmpresa::create($datos);
    }

    public function pagarNominaMasiva($empresaId, $usuarioId, $facturasIds, $cuentaBancariaId, ?string $fecha = null)
    {
        return DB::transaction(function () use ($empresaId, $usuarioId, $facturasIds, $cuentaBancariaId, $fecha) {

            // SEGURIDAD: validar que la cuenta bancaria pertenezca a la empresa del usuario.
            $cuentaPertenece = CuentaBancariaEmpresa::where('id', $cuentaBancariaId)
                ->where('empresa_id', $empresaId)
                ->exists();

            if (!$cuentaPertenece) {
                throw TesoreriaException::noEncontrado("Cuenta bancaria no pertenece a la empresa.");
            }

            // Lock pesimista sobre las facturas solicitadas para evitar doble pago concurrente.
            // tipo='COMPRA' explicito: sin esto, una factura de VENTA con proveedor_id "espejo"
            // (RUT compartido con un Cliente, ver ProveedorAislamientoVentaCompraTest) podia
            // colarse en facturasIds y quedar marcada PAGADA por un pago de nómina a proveedores.
            $facturas = Factura::where('empresa_id', $empresaId)
                ->where('tipo', 'COMPRA')
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
            $fechaHoy = $fecha ?? now()->format('Y-m-d');

            $ids = [];
            foreach ($facturas as $factura) {
                /** @var Factura $factura */
                $ids[] = $factura->id;
                $totalNomina += $factura->monto_bruto;
                $numerosFacturas[] = $factura->numero_factura;
            }

            // Batch UPDATE: marca todas como PAGADAS en una sola query en lugar de N saves.
            Factura::whereIn('id', $ids)->where('empresa_id', $empresaId)->update(['estado' => 'PAGADA']);

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

            // Vincula el asiento a las facturas para que una anulación posterior revierta su estado.
            Factura::whereIn('id', $ids)->where('empresa_id', $empresaId)->update(['asiento_pago_id' => $asiento->id]);

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

    public function procesarCartola(int $empresaId, int $usuarioId, int $cuentaBancariaId, $archivo): array
    {
        // Ya no exige cuenta contable: el import solo deja movimientos PENDIENTE, se contabiliza en Mesa de Conciliación.
        $this->obtenerCuentaBancaria($empresaId, $cuentaBancariaId);

        try {
            // DB::transaction asegura rollback ante excepción (antes quedaba abierta con begin/commit manual).
            return DB::transaction(function () use (
                $empresaId, $cuentaBancariaId, $archivo
            ): array {
                // IOFactory detecta el formato real (CSV/XLS/XLSX) por contenido, no por extensión.
                $hoja = IOFactory::load($archivo->getRealPath())->getActiveSheet();
                $importados = 0;
                $ignorados = 0;
                $ultimaFila = $hoja->getHighestDataRow();

                // Cartolas reales traen metadata antes del encabezado real y Cargos/Abonos en columnas separadas.
                $encabezado = $this->localizarEncabezadoCartola($hoja, $ultimaFila);

                for ($numeroFila = $encabezado['fila'] + 1; $numeroFila <= $ultimaFila; $numeroFila++) {
                    $celdaFecha = $hoja->getCell($encabezado['colFecha'] . $numeroFila);
                    $celdaDescripcion = $hoja->getCell($encabezado['colDescripcion'] . $numeroFila);

                    $valorFecha = $celdaFecha->getCalculatedValue();
                    $descripcion = substr(trim((string) $celdaDescripcion->getCalculatedValue()), 0, 255);

                    if ($valorFecha === null && $descripcion === '') continue;

                    if ($encabezado['colMonto'] !== null) {
                        $monto = (float) $hoja->getCell($encabezado['colMonto'] . $numeroFila)->getCalculatedValue();
                    } else {
                        $montoCargo = $encabezado['colCargo'] !== null
                            ? (float) $hoja->getCell($encabezado['colCargo'] . $numeroFila)->getCalculatedValue()
                            : 0.0;
                        $montoAbono = $encabezado['colAbono'] !== null
                            ? (float) $hoja->getCell($encabezado['colAbono'] . $numeroFila)->getCalculatedValue()
                            : 0.0;

                        // Se normaliza el signo acá: cada banco exporta Cargos/Abonos con convención distinta.
                        $monto = $montoCargo != 0 ? -abs($montoCargo) : ($montoAbono != 0 ? abs($montoAbono) : 0.0);
                    }

                    // Excel/XLS guarda fechas como número serial; CSV las trae como texto.
                    $fecha = (is_numeric($valorFecha) && FechaExcel::isDateTime($celdaFecha))
                        ? FechaExcel::excelToDateTimeObject($valorFecha)->format('Y-m-d')
                        : date('Y-m-d', strtotime(str_replace('/', '-', (string) $valorFecha)));

                    if ($monto == 0) continue;

                    $nroDocumento = $encabezado['colDocumento'] !== null
                        ? trim((string) $hoja->getCell($encabezado['colDocumento'] . $numeroFila)->getCalculatedValue())
                        : null;
                    if ($nroDocumento === '') $nroDocumento = null;

                    if ($this->existeMovimientoDuplicado($empresaId, $cuentaBancariaId, $fecha, $descripcion, $monto, $nroDocumento)) {
                        $ignorados++;
                        continue;
                    }

                    // Fecha real del movimiento, no la de hoy: se contabiliza recién en Mesa de Conciliación.
                    DB::table('movimientos_bancarios')->insert([
                        'empresa_id' => $empresaId,
                        'cuenta_bancaria_id' => $cuentaBancariaId,
                        'fecha' => $fecha,
                        'descripcion' => $descripcion,
                        'nro_documento' => $nroDocumento,
                        'cargo' => $monto < 0 ? abs($monto) : 0,
                        'abono' => $monto > 0 ? abs($monto) : 0,
                        'estado' => 'PENDIENTE',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $importados++;
                }

                return [
                    'importados' => $importados,
                    'ignorados' => $ignorados
                ];
            });
        } catch (TesoreriaException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw TesoreriaException::regla("El archivo contiene errores y la importación fue abortada. Error: " . $e->getMessage());
        }
    }

    /** N° de documento como clave de dedup cuando está disponible: fecha+descripción no alcanza (transacciones idénticas el mismo día). */
    private function existeMovimientoDuplicado(
        int $empresaId,
        int $cuentaBancariaId,
        string $fecha,
        string $descripcion,
        float $monto,
        ?string $nroDocumento
    ): bool {
        $query = DB::table('movimientos_bancarios')
            ->where('empresa_id', $empresaId)
            ->where('cuenta_bancaria_id', $cuentaBancariaId);

        if ($nroDocumento !== null) {
            return (clone $query)->where('nro_documento', $nroDocumento)->exists();
        }

        return $query
            ->where('fecha', $fecha)
            ->where('descripcion', $descripcion)
            ->where('cargo', $monto < 0 ? abs($monto) : 0)
            ->where('abono', $monto > 0 ? abs($monto) : 0)
            ->exists();
    }

    /**
     * Ubica el encabezado real por nombre de columna (no posición fija): los bancos exportan metadata antes de la tabla.
     * @return array{fila:int, colFecha:string, colDescripcion:string, colMonto:?string, colCargo:?string, colAbono:?string, colDocumento:?string}
     */
    private function localizarEncabezadoCartola(Worksheet $hoja, int $ultimaFila): array
    {
        $filasARevisar = min($ultimaFila, 30);

        for ($fila = 1; $fila <= $filasARevisar; $fila++) {
            $mapa = [];
            foreach ($hoja->getRowIterator($fila, $fila) as $row) {
                foreach ($row->getCellIterator('A', $hoja->getHighestColumn()) as $celda) {
                    $texto = $this->normalizarEncabezado((string) $celda->getCalculatedValue());
                    if ($texto !== '') {
                        $mapa[$texto] = $celda->getColumn();
                    }
                }
            }

            $colFecha = $mapa['fecha'] ?? null;
            $colDescripcion = $mapa['descripcion'] ?? $mapa['detalle'] ?? $mapa['glosa'] ?? null;

            if ($colFecha === null || $colDescripcion === null) {
                continue;
            }

            $colMonto = $mapa['monto'] ?? $mapa['importe'] ?? null;
            $colCargo = $mapa['cargos'] ?? $mapa['cargo'] ?? $mapa['debitos'] ?? $mapa['debito'] ?? null;
            $colAbono = $mapa['abonos'] ?? $mapa['abono'] ?? $mapa['creditos'] ?? $mapa['credito'] ?? null;
            $colDocumento = $mapa['n doc'] ?? $mapa['nro documento'] ?? $mapa['numero documento'] ?? $mapa['nro doc'] ?? $mapa['documento'] ?? null;

            if ($colMonto === null && $colCargo === null && $colAbono === null) {
                continue;
            }

            return [
                'fila' => $fila,
                'colFecha' => $colFecha,
                'colDescripcion' => $colDescripcion,
                'colMonto' => $colMonto,
                'colCargo' => $colCargo,
                'colAbono' => $colAbono,
                'colDocumento' => $colDocumento,
            ];
        }

        throw TesoreriaException::regla(
            "No se pudo identificar la estructura de la cartola (se esperan columnas Fecha y Descripción, más Monto o Cargos/Abonos). Verifica que el archivo sea el reporte de movimientos del banco."
        );
    }

    private function normalizarEncabezado(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', '.', '°', 'n°'],
            ['a', 'e', 'i', 'o', 'u', 'n', '', '', 'n'],
            $texto
        );

        return trim($texto);
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

    /** Bloquea (lockForUpdate) y rechaza si ya está CONCILIADO -- evita doble conciliación en carrera. Llamar dentro de una transacción. */
    public function obtenerMovimientoParaConciliar(int $empresaId, int $id)
    {
        $mov = DB::table('movimientos_bancarios')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->lockForUpdate()
            ->first();

        if (!$mov) throw TesoreriaException::noEncontrado("Movimiento bancario no encontrado.");

        // Whitelist en vez de blacklist: CONCILIADO_ANTICIPO tambien debe bloquear,
        // sino el mismo movimiento se puede aplicar dos veces (doble anticipo o
        // anticipo + conciliacion directa sobre el mismo deposito).
        if (isset($mov->estado) && $mov->estado !== 'PENDIENTE') {
            throw TesoreriaException::regla("El movimiento bancario ya fue conciliado (estado: {$mov->estado}).");
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

    public function obtenerMovimientosDescartados(int $empresaId, int $cuentaBancariaId)
    {
        $this->obtenerCuentaBancaria($empresaId, $cuentaBancariaId);
        return DB::table('movimientos_bancarios')
            ->where('empresa_id', $empresaId)
            ->where('cuenta_bancaria_id', $cuentaBancariaId)
            ->where('estado', 'DESCARTADO')
            ->orderBy('fecha', 'desc')
            ->get();
    }

    /**
     * Anticipos PENDIENTE de proveedor (egreso a pagar) y de cliente (ingreso a cobrar) en
     * una sola lista, cada fila tageada con 'tipo' para que el caller sepa a que tabla
     * pertenece (anticipos_proveedores/anticipos_clientes comparten estados pero no tabla).
     */
    public function obtenerAnticiposPendientes(int $empresaId)
    {
        $proveedores = DB::table('anticipos_proveedores as a')
            ->join('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->where('a.empresa_id', $empresaId)
            ->where('a.estado', 'PENDIENTE')
            ->selectRaw("a.id, a.monto, a.referencia, a.created_at, 'proveedor' as tipo, p.razon_social as entidad_nombre");

        $clientes = DB::table('anticipos_clientes as a')
            ->join('clientes as c', 'c.id', '=', 'a.cliente_id')
            ->where('a.empresa_id', $empresaId)
            ->where('a.estado', 'PENDIENTE')
            ->selectRaw("a.id, a.monto, a.referencia, a.created_at, 'cliente' as tipo, c.razon_social as entidad_nombre");

        return $proveedores->unionAll($clientes)->orderBy('created_at')->get();
    }

    public function vincularMovimientoAAnticipo(int $empresaId, int $movimientoId, int $anticipoId, string $tipo = 'proveedor')
    {
        $tabla = $tipo === 'cliente' ? 'anticipos_clientes' : 'anticipos_proveedores';

        DB::transaction(function () use ($empresaId, $movimientoId, $anticipoId, $tabla) {
            // Lock pesimista + guard de estado para evitar doble conciliación en carrera.
            $movimiento = $this->obtenerMovimientoParaConciliar($empresaId, $movimientoId);

            // obtenerMovimientoParaConciliar ya rechaza CONCILIADO; validamos también el estado anticipo.
            $anticipo = DB::table($tabla)
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

            DB::table($tabla)
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