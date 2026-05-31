<?php

namespace Tests\Unit\Sii;

use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Test unitario de la maquina de niveles de alerta.
 * Hereda de Tests\TestCase (no PHPUnit\Framework\TestCase) porque Eloquent
 * necesita el container de Laravel para resolver config() durante el boot del modelo.
 * No usa BD: instancia el modelo en memoria y verifica el match de fechas.
 */
class SiiCertificadoEmpresaNivelAlertaTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function certConVencimiento(Carbon $validoHasta): SiiCertificadoEmpresa
    {
        $cert = new SiiCertificadoEmpresa();
        $cert->valido_hasta = $validoHasta;

        return $cert;
    }

    private function fijarAhora(string $iso): void
    {
        Carbon::setTestNow(Carbon::parse($iso));
    }

    public function test_nivel_VENCIDO_para_fecha_pasada(): void
    {
        $this->fijarAhora('2026-05-23 10:00:00');
        $cert = $this->certConVencimiento(Carbon::parse('2026-05-22 09:00:00'));

        $this->assertSame(SiiCertificadoEmpresa::ALERTA_VENCIDO, $cert->nivelAlerta());
    }

    public function test_nivel_CRITICA_T1_dia_0(): void
    {
        // Hoy es 2026-05-23 10:00; cert vence hoy a las 23:59 -> dias=0.
        $this->fijarAhora('2026-05-23 10:00:00');
        $cert = $this->certConVencimiento(Carbon::parse('2026-05-23 23:59:00'));

        $this->assertSame(SiiCertificadoEmpresa::ALERTA_CRITICA_T1, $cert->nivelAlerta());
    }

    public function test_nivel_CRITICA_T1_dia_1(): void
    {
        $this->fijarAhora('2026-05-23 10:00:00');
        $cert = $this->certConVencimiento(Carbon::parse('2026-05-24 10:00:00'));

        $this->assertSame(SiiCertificadoEmpresa::ALERTA_CRITICA_T1, $cert->nivelAlerta());
    }

    public function test_nivel_CRITICA_T7_entre_2_y_7_dias(): void
    {
        $this->fijarAhora('2026-05-23 10:00:00');

        foreach ([2, 4, 7] as $dias) {
            $cert = $this->certConVencimiento(Carbon::parse('2026-05-23 10:00:00')->addDays($dias));
            $this->assertSame(
                SiiCertificadoEmpresa::ALERTA_CRITICA_T7,
                $cert->nivelAlerta(),
                "Esperaba CRITICA_T7 a {$dias} dias"
            );
        }
    }

    public function test_nivel_ALTA_T15_entre_8_y_15(): void
    {
        $this->fijarAhora('2026-05-23 10:00:00');

        foreach ([8, 10, 15] as $dias) {
            $cert = $this->certConVencimiento(Carbon::parse('2026-05-23 10:00:00')->addDays($dias));
            $this->assertSame(
                SiiCertificadoEmpresa::ALERTA_ALTA_T15,
                $cert->nivelAlerta(),
                "Esperaba ALTA_T15 a {$dias} dias"
            );
        }
    }

    public function test_nivel_MEDIA_T30_entre_16_y_30(): void
    {
        $this->fijarAhora('2026-05-23 10:00:00');

        foreach ([16, 22, 30] as $dias) {
            $cert = $this->certConVencimiento(Carbon::parse('2026-05-23 10:00:00')->addDays($dias));
            $this->assertSame(
                SiiCertificadoEmpresa::ALERTA_MEDIA_T30,
                $cert->nivelAlerta(),
                "Esperaba MEDIA_T30 a {$dias} dias"
            );
        }
    }

    public function test_nivel_BAJA_T60_entre_31_y_60(): void
    {
        $this->fijarAhora('2026-05-23 10:00:00');

        foreach ([31, 45, 60] as $dias) {
            $cert = $this->certConVencimiento(Carbon::parse('2026-05-23 10:00:00')->addDays($dias));
            $this->assertSame(
                SiiCertificadoEmpresa::ALERTA_BAJA_T60,
                $cert->nivelAlerta(),
                "Esperaba BAJA_T60 a {$dias} dias"
            );
        }
    }

    public function test_nivel_SIN_ALERTA_mayor_a_60(): void
    {
        $this->fijarAhora('2026-05-23 10:00:00');

        foreach ([61, 90, 365] as $dias) {
            $cert = $this->certConVencimiento(Carbon::parse('2026-05-23 10:00:00')->addDays($dias));
            $this->assertSame(
                SiiCertificadoEmpresa::ALERTA_SIN_ALERTA,
                $cert->nivelAlerta(),
                "Esperaba SIN_ALERTA a {$dias} dias"
            );
        }
    }

    public function test_dia_60_exacto_es_BAJA_T60_no_SIN_ALERTA(): void
    {
        $this->fijarAhora('2026-05-23 10:00:00');
        $cert = $this->certConVencimiento(Carbon::parse('2026-05-23 10:00:00')->addDays(60));

        $this->assertSame(SiiCertificadoEmpresa::ALERTA_BAJA_T60, $cert->nivelAlerta());
    }

    public function test_dia_61_exacto_es_SIN_ALERTA(): void
    {
        $this->fijarAhora('2026-05-23 10:00:00');
        $cert = $this->certConVencimiento(Carbon::parse('2026-05-23 10:00:00')->addDays(61));

        $this->assertSame(SiiCertificadoEmpresa::ALERTA_SIN_ALERTA, $cert->nivelAlerta());
    }
}
