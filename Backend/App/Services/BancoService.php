<?php
namespace App\Services;

use App\Repositories\BancoRepository;
use Exception;

class BancoService
{
    private $repo;

    public function __construct()
    {
        $this->repo = new BancoRepository();
    }

    public function obtenerCuentasEmpresa($empresaId)
    {
        return $this->repo->getCuentasEmpresa($empresaId);
    }

    public function procesarNominaPagos($facturasIds, $cuentaBancariaId)
    {
        if (empty($facturasIds))
            throw new Exception("No hay facturas seleccionadas.");

        $cuentaBanco = $this->repo->getCuentaBancaria($cuentaBancariaId);
        if (!$cuentaBanco)
            throw new Exception("Cuenta bancaria de origen no válida.");
        if (empty($cuentaBanco['cuenta_contable']))
            throw new Exception("La cuenta bancaria no tiene asignada una cuenta contable en el Plan Maestro.");

        $facturas = $this->repo->getFacturasPorIds($facturasIds);
        if (count($facturas) !== count($facturasIds)) {
            throw new Exception("Algunas facturas ya fueron pagadas o no existen.");
        }

        $this->repo->iniciarTransaccion();

        try {
            $totalNomina = "0.00";
            $fechaActual = date('Y-m-d');
            $codigoUnicoAsiento = $this->repo->generarCodigoAsiento($fechaActual);
            $glosa = "Pago Nómina Masiva: " . count($facturas) . " facturas.";
            $asientoId = $this->repo->crearAsientoContable($fechaActual, $glosa, $codigoUnicoAsiento);

            foreach ($facturas as $fac) {
                $montoStr = number_format((float) $fac['monto_bruto'], 2, '.', '');
                $totalNomina = bcadd($totalNomina, $montoStr, 2);

                $this->repo->marcarFacturaPagada($fac['id']);
                $this->repo->registrarPagoFactura($fac['id'], $cuentaBancariaId, $asientoId, $fechaActual, $montoStr);
                $this->repo->agregarDetalleAsiento($asientoId, '210101', $montoStr, "0.00");
            }

            $this->repo->agregarDetalleAsiento($asientoId, $cuentaBanco['cuenta_contable'], "0.00", $totalNomina);

            $this->repo->registrarMovimientoCartola([
                'cuenta_bancaria_id' => $cuentaBancariaId,
                'fecha' => $fechaActual,
                'hora' => date('H:i:s'), // <-- AÑADIDO: HORA EN NÓMINA
                'descripcion' => "Pago Nómina Transferencias Masivas",
                'nro_documento' => "NOM-" . time(),
                'cargo' => $totalNomina,
                'abono' => "0.00",
                'saldo_historico' => "0.00",
                'estado' => 'CONCILIADO',
                'asiento_id' => $asientoId
            ]);

            $nuevoSaldoCaja = bcsub($cuentaBanco['saldo_actual'], $totalNomina, 2);
            $this->repo->actualizarSaldoCuenta($cuentaBancariaId, $nuevoSaldoCaja);

            $this->repo->confirmarTransaccion();

            return [
                'success' => true,
                'mensaje' => "Nómina contabilizada con éxito.",
                'total_pagado' => $totalNomina,
                'asiento_id' => $asientoId
            ];

        } catch (Exception $e) {
            $this->repo->revertirTransaccion();
            throw new Exception("Error al procesar el pago: " . $e->getMessage());
        }
    }

    public function importarCartolaExcel($cuentaBancariaId, $archivoRuta)
    {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new Exception("Librería PhpSpreadsheet no instalada.");
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($archivoRuta);
            $hoja = $spreadsheet->getActiveSheet();
            $filas = $hoja->toArray();

            $this->repo->iniciarTransaccion();

            $registrosImportados = 0;
            $registrosOmitidos = 0;
            $cuenta = $this->repo->getCuentaBancaria($cuentaBancariaId);
            $saldoActual = $cuenta['saldo_actual'];
            $headerRowIndex = -1;
            $colMap = [];
            
            for ($i = 0; $i < min(40, count($filas)); $i++) {
                $filaTxt = strtolower(implode(" ", array_map('strval', $filas[$i])));

                if (strpos($filaTxt, 'fecha') !== false && (strpos($filaTxt, 'descrip') !== false || strpos($filaTxt, 'detalle') !== false)) {
                    $headerRowIndex = $i;
                    foreach ($filas[$i] as $idx => $colName) {
                        $colName = strtolower(trim((string) $colName));
                        if (strpos($colName, 'fecha') !== false)
                            $colMap['fecha'] = $idx;
                        elseif (strpos($colName, 'descrip') !== false || strpos($colName, 'detalle') !== false)
                            $colMap['descripcion'] = $idx;
                        elseif (strpos($colName, 'doc') !== false || strpos($colName, 'comprob') !== false || strpos($colName, 'operaci') !== false)
                            $colMap['documento'] = $idx;
                        elseif (strpos($colName, 'cargo') !== false && strpos($colName, 'abono') !== false)
                            $colMap['tipo_movimiento'] = $idx;
                        elseif (strpos($colName, 'cargo') !== false || strpos($colName, 'cheque') !== false)
                            $colMap['cargo'] = $idx;
                        elseif (strpos($colName, 'abono') !== false || strpos($colName, 'depósito') !== false || strpos($colName, 'deposito') !== false)
                            $colMap['abono'] = $idx;
                        elseif (strpos($colName, 'monto') !== false)
                            $colMap['monto'] = $idx;
                        elseif (strpos($colName, 'saldo') !== false)
                            $colMap['saldo'] = $idx;
                    }
                    break;
                }
            }

            if ($headerRowIndex === -1 || !isset($colMap['fecha'])) {
                throw new Exception("No se detectó una estructura válida. El Excel debe contener al menos 'Fecha' y 'Descripción'.");
            }

            for ($i = $headerRowIndex + 1; $i < count($filas); $i++) {
                $fila = $filas[$i];

                $rawFecha = $fila[$colMap['fecha']] ?? null;
                if (empty($rawFecha))
                    continue;

                $fechaFormateada = date('Y-m-d', strtotime(str_replace('/', '-', $rawFecha)));
                if ($fechaFormateada === '1970-01-01' || !$fechaFormateada)
                    continue;

                $horaFormateada = null;
                if (strpos($rawFecha, ':') !== false) {
                    $horaFormateada = date('H:i:s', strtotime(str_replace('/', '-', $rawFecha)));
                }

                $descripcion = trim((string) ($fila[$colMap['descripcion']] ?? 'Movimiento Importado'));
                $documento = isset($colMap['documento']) ? trim((string) $fila[$colMap['documento']]) : '';
                $saldoFila = isset($colMap['saldo']) ? $this->limpiarNumero($fila[$colMap['saldo']]) : '0.00';

                $cargo = "0.00";
                $abono = "0.00";

                // CASO A: Monto + Letra (C/A)
                if (isset($colMap['monto']) && isset($colMap['tipo_movimiento'])) {
                    $montoRaw = $this->limpiarNumero($fila[$colMap['monto']]);
                    $tipoMov = strtoupper(trim((string) $fila[$colMap['tipo_movimiento']]));

                    if ($tipoMov === 'C' || $tipoMov === 'CARGO') {
                        $cargo = abs((float) $montoRaw);
                    } else {
                        $abono = abs((float) $montoRaw);
                    }
                }
                // CASO B: Columna única "Monto"
                elseif (isset($colMap['monto']) && !isset($colMap['cargo']) && !isset($colMap['abono'])) {
                    $montoOriginalStr = (string) $fila[$colMap['monto']];
                    $montoRaw = $this->limpiarNumero($montoOriginalStr);
                    if (strpos($montoOriginalStr, '-') !== false) {
                        $cargo = abs((float) $montoRaw);
                    } else {
                        $abono = abs((float) $montoRaw);
                    }
                }
                // CASO C: Columnas separadas
                else {
                    if (isset($colMap['cargo'])) {
                        $cargoRaw = $this->limpiarNumero($fila[$colMap['cargo']]);
                        $cargo = abs((float) $cargoRaw);
                    }
                    if (isset($colMap['abono'])) {
                        $abonoRaw = $this->limpiarNumero($fila[$colMap['abono']]);
                        $abono = abs((float) $abonoRaw);
                    }
                }

                $cargoStr = number_format((float) $cargo, 2, '.', '');
                $abonoStr = number_format((float) $abono, 2, '.', '');

                if ($abonoStr === "0.00" && $cargoStr === "0.00")
                    continue;

                if ($this->repo->existeMovimiento($cuentaBancariaId, $fechaFormateada, $cargoStr, $abonoStr, $saldoFila)) {
                    $registrosOmitidos++;
                    continue;
                }

                if ($abonoStr > 0)
                    $saldoActual = bcadd($saldoActual, $abonoStr, 2);
                if ($cargoStr > 0)
                    $saldoActual = bcsub($saldoActual, $cargoStr, 2);

                $this->repo->registrarMovimientoCartola([
                    'cuenta_bancaria_id' => $cuentaBancariaId,
                    'fecha' => $fechaFormateada,
                    'hora' => $horaFormateada,
                    'descripcion' => $descripcion,
                    'nro_documento' => $documento,
                    'cargo' => $cargoStr,
                    'abono' => $abonoStr,
                    'saldo_historico' => $saldoFila,
                    'estado' => 'PENDIENTE',
                    'asiento_id' => null
                ]);
                $registrosImportados++;
            }

            $this->repo->actualizarSaldoCuenta($cuentaBancariaId, $saldoActual);
            $this->repo->confirmarTransaccion();

            $msgFinal = "Se importaron $registrosImportados movimientos nuevos.";
            if ($registrosOmitidos > 0) $msgFinal .= " Se omitieron $registrosOmitidos ya existentes.";

            return [
                'success' => true,
                'mensaje' => $msgFinal
            ];

        } catch (Exception $e) {
            $this->repo->revertirTransaccion();
            throw new Exception("Error al leer el Excel. Verifique formato bancario. Detalles: " . $e->getMessage());
        }
    }

    public function registrarIngresoManual($datos)
    {
        $cuentaBanco = $this->repo->getCuentaBancaria($datos['cuenta_bancaria_id']);
        if (!$cuentaBanco)
            throw new Exception("Cuenta bancaria no válida.");

        $this->repo->iniciarTransaccion();

        try {
            $montoIngreso = number_format((float) $datos['monto'], 2, '.', '');
            $nuevoSaldo = bcadd($cuentaBanco['saldo_actual'], $montoIngreso, 2);

            $this->repo->registrarMovimientoCartola([
                'cuenta_bancaria_id' => $datos['cuenta_bancaria_id'],
                'fecha' => $datos['fecha'],
                'hora' => date('H:i:s'), 
                'descripcion' => $datos['descripcion'],
                'nro_documento' => $datos['nro_documento'] ?? 'MANUAL',
                'cargo' => "0.00",
                'abono' => $montoIngreso,
                'saldo_historico' => $nuevoSaldo,
                'estado' => 'CONCILIADO',
                'asiento_id' => null
            ]);
            $this->repo->actualizarSaldoCuenta($datos['cuenta_bancaria_id'], $nuevoSaldo);

            $this->repo->confirmarTransaccion();

            return ['success' => true, 'mensaje' => 'Abono registrado con éxito'];
        } catch (Exception $e) {
            $this->repo->revertirTransaccion();
            throw new Exception("Error al registrar ingreso: " . $e->getMessage());
        }
    }

    private function limpiarNumero($valor) {
        if (empty($valor) && $valor !== '0' && $valor !== 0) return "0.00";
        
        $valor = preg_replace('/[^0-9\,\.\-]/', '', (string)$valor);
        if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
            $valor = str_replace('.', '', $valor); 
            $valor = str_replace(',', '.', $valor); 
        } elseif (strpos($valor, ',') !== false) {
            $valor = str_replace(',', '.', $valor);
        }
        return number_format((float)$valor, 2, '.', '');
    }

    // --- LÓGICA DE CONCILIACIÓN ---
    public function obtenerMovimientosPendientes($cuentaId) {
        return $this->repo->getMovimientosPendientes($cuentaId);
    }

    public function obtenerCuentasImputables() {
        return $this->repo->getCuentasImputables();
    }

    public function conciliarMovimientoDirecto($datos) {
        $movimientoId = $datos['movimiento_id'];
        $cuentaCodigo = $datos['cuenta_codigo'];
        $glosaUsuario = trim($datos['glosa'] ?? '');
        $centroCostoId = !empty($datos['centro_costo_id']) ? $datos['centro_costo_id'] : null;
        $empleadoNombre = !empty($datos['empleado_nombre']) ? trim($datos['empleado_nombre']) : null;

        $mov = $this->repo->getMovimientoById($movimientoId);
        if (!$mov || $mov['estado'] !== 'PENDIENTE') throw new Exception("Movimiento no válido o ya conciliado.");

        $cuentaBanco = $this->repo->getCuentaBancaria($mov['cuenta_bancaria_id']);
        if (!$cuentaBanco || !$cuentaBanco['cuenta_contable']) throw new Exception("La cuenta bancaria no tiene cuenta contable asociada.");

        $this->repo->iniciarTransaccion();
        try {
            $codigoUnicoAsiento = $this->repo->generarCodigoAsiento($mov['fecha']);
            $glosaFinal = $glosaUsuario ? $glosaUsuario : $mov['descripcion'];
            $asientoId = $this->repo->crearAsientoContable($mov['fecha'], $glosaFinal, $codigoUnicoAsiento, $centroCostoId, $empleadoNombre);

            if ($mov['cargo'] > 0) {
                $this->repo->agregarDetalleAsiento($asientoId, $cuentaCodigo, $mov['cargo'], "0.00");
                $this->repo->agregarDetalleAsiento($asientoId, $cuentaBanco['cuenta_contable'], "0.00", $mov['cargo']);
            } elseif ($mov['abono'] > 0) {
                $this->repo->agregarDetalleAsiento($asientoId, $cuentaBanco['cuenta_contable'], $mov['abono'], "0.00");
                $this->repo->agregarDetalleAsiento($asientoId, $cuentaCodigo, "0.00", $mov['abono']);
            }
            $this->repo->actualizarEstadoMovimiento($movimientoId, 'CONCILIADO', $asientoId);
            if ($glosaUsuario && $glosaUsuario !== $mov['descripcion']) {
                $this->repo->actualizarDescripcionMovimiento($movimientoId, $glosaUsuario);
            }

            $this->repo->confirmarTransaccion();
            return ['success' => true, 'mensaje' => 'Movimiento contabilizado con éxito.'];
        } catch (Exception $e) {
            $this->repo->revertirTransaccion();
            throw new Exception("Error al conciliar: " . $e->getMessage());
        }
    }

    public function ignorarMovimiento($movimientoId) {
        $mov = $this->repo->getMovimientoById($movimientoId);
        if (!$mov || $mov['estado'] !== 'PENDIENTE') throw new Exception("Movimiento no válido.");
        
        $this->repo->actualizarEstadoMovimiento($movimientoId, 'IGNORADO', null);
        return ['success' => true, 'mensaje' => 'Movimiento omitido de la contabilidad.'];
    }
}