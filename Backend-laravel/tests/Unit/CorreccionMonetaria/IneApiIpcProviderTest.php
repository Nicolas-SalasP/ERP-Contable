<?php

namespace Tests\Unit\CorreccionMonetaria;

use App\Domains\CorreccionMonetaria\Providers\IneApiIpcProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * NOTA: IneApiIpcProvider es actualmente un stub sin implementar (no realiza
 * ninguna llamada HTTP real al INE todavía -- ver lanzarNoImplementado()).
 * Por eso este test NO cubre parseo de respuesta HTTP exitosa/corrupta con
 * Http::fake() como en otras integraciones externas del proyecto: no hay
 * lógica de parseo que ejercitar aún. En su lugar, fija el contrato de
 * fallo actual (mensaje de excepción claro y accionable) para que, cuando
 * alguien implemente la llamada real a la API del INE, este test falle y
 * obligue a actualizarlo -- evitando que el stub quede sin cobertura
 * silenciosamente.
 */
class IneApiIpcProviderTest extends TestCase
{
    private IneApiIpcProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new IneApiIpcProvider();
    }

    public function test_get_variacion_mensual_lanza_excepcion_clara_de_no_implementado(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no está implementado aún');

        $this->provider->getVariacionMensual(2026, 5);
    }

    public function test_get_variacion_acumulada_lanza_excepcion_clara_de_no_implementado(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no está implementado aún');

        $this->provider->getVariacionAcumulada(2026, 5);
    }

    public function test_get_factor_multiplicador_propaga_la_excepcion_de_variacion_mensual(): void
    {
        // getFactorMultiplicador depende de getVariacionMensual: si esa falla,
        // no debe devolver un factor silencioso (ej. 1.0) sino propagar el error.
        $this->expectException(RuntimeException::class);

        $this->provider->getFactorMultiplicador(2026, 5);
    }

    public function test_tiene_indice_devuelve_false_porque_no_hay_datos_disponibles(): void
    {
        $this->assertFalse($this->provider->tieneIndice(2026, 5));
    }

    public function test_get_nombre_identifica_el_proveedor_como_no_implementado(): void
    {
        $this->assertSame('API INE (no implementado aún)', $this->provider->getNombre());
    }
}
