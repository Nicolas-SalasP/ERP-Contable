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

            // Vincula el asiento de pago a las facturas para que una anulación posterior
            // (Anulación General) pueda revertir su estado automáticamente.
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
        // Precondición FUERA de la transacción: valida pertenencia de la cuenta.
        // Ya no exige cuenta contable configurada acá -- el import solo deja los
        // movimientos PENDIENTE, la contabilización (con su cuenta contrapartida
        // elegida por movimiento) ocurre despues en Mesa de Conciliación.
        $this->obtenerCuentaBancaria($empresaId, $cuentaBancariaId);

        try {
            // DB::transaction garantiza el rollback ante cualquier excepción (antes se
            // usaba beginTransaction()/commit() con un catch que no capturaba las
            // TesoreriaException por falta del import, dejando la transacción abierta).
            return DB::transaction(function () use (
                $empresaId, $cuentaBancariaId, $archivo
            ): array {
                // IOFactory detecta el formato real por contenido (CSV, XLS o XLSX),
                // no por la extensión del archivo -> soporta la cartola tal cual la
                // exporta el banco sin pedirle al usuario que la convierta a CSV.
                $hoja = IOFactory::load($archivo->getRealPath())->getActiveSheet();
                $importados = 0;
                $ignorados = 0;
                $ultimaFila = $hoja->getHighestDataRow();

                // Cartolas reales (ej. Scotiabank) traen un bloque de metadata (Nombre
                // Empresa, Número Línea, Saldo Disponible, etc.) ANTES del encabezado
                // real de la tabla, y separan Cargos/Abonos en dos columnas en vez de
                // un solo monto con signo -- asumir fila 1 = encabezado y columna C =
                // monto (como antes) hacía que todo el archivo importara 0 filas.
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

                        // Cargos/Abonos vienen en columnas separadas; se normaliza el
                        // signo acá en vez de confiar en como cada banco los exporte
                        // (algunos traen el Cargo ya negativo, otros en positivo).
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

                    // Se deja PENDIENTE con la fecha real del movimiento (no la de hoy):
                    // la contabilización recien ocurre en Mesa de Conciliación, y esa
                    // fecha es la que debe quedar en el asiento cuando eso pase.
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

    /**
     * Un mismo N° de documento del banco identifica un movimiento de forma
     * unica -- se usa como clave de dedup cuando el archivo lo trae, porque
     * fecha+descripcion NO alcanza: es comun tener dos transacciones reales
     * el mismo dia, con la misma glosa y el mismo monto (ej. dos TEF de
     * $50.000 al mismo destinatario en fechas distintas de una nomina).
     */
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
     * Encuentra la fila de encabezado real de la cartola y mapea sus columnas por
     * nombre (no por posición fija) -- los bancos exportan con bloques de metadata
     * antes de la tabla (Nombre Empresa, Saldo Disponible, etc.) y algunos separan
     * Cargos/Abonos en dos columnas en vez de un solo monto con signo.
     *
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