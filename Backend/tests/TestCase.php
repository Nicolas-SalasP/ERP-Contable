<?php
namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Exception;

class TestCase extends BaseTestCase {
    
    // Generador de errores con el formato exacto que solicitaste
    protected function lanzarErrorDetallado(string $tipo, string $modulo, string $archivo, string $funcion, string $descripcion, string $detalle, int $linea) {
        $mensaje = "\n=========================================\n";
        $mensaje .= "TEST FAILED\n\n";
        $mensaje .= "Tipo de error: {$tipo}\n";
        $mensaje .= "Módulo: {$modulo}\n";
        $mensaje .= "Archivo: {$archivo}\n";
        $mensaje .= "Función: {$funcion}()\n\n";
        $mensaje .= "Descripción:\n{$descripcion}\n\n";
        $mensaje .= "Detalle:\n{$detalle}\n\n";
        $mensaje .= "Ubicación:\nLínea {$linea}\n";
        $mensaje .= "=========================================\n";
        
        $this->fail($mensaje);
    }

    // Aserción personalizada para Asientos Contables
    protected function assertEquilibrioContable($debe, $haber, $archivo, $linea) {
        if ($debe !== $haber) {
            $this->lanzarErrorDetallado(
                'Integridad contable',
                'Contabilidad',
                $archivo,
                'validarAsiento()',
                'El asiento contable generado no mantiene equilibrio Debe/Haber.',
                "Debe: {$debe}\nHaber: {$haber}",
                $linea
            );
        }
        $this->assertTrue(true);
    }
}