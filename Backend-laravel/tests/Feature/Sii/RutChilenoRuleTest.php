<?php

namespace Tests\Feature\Sii;

use App\Domains\Sii\Rules\RutChileno;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RutChilenoRuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Endpoint inline solo para este test: valida un campo 'rut' usando la regla.
        Route::post('_test/sii/validar-rut', function (\Illuminate\Http\Request $request) {
            $data = $request->validate([
                'rut' => ['required', new RutChileno()],
            ]);

            return response()->json(['rut' => $data['rut']]);
        });
    }

    public function test_request_con_rut_valido_pasa_validacion(): void
    {
        $response = $this->postJson('/_test/sii/validar-rut', [
            'rut' => '76086428-5',
        ]);

        $response->assertStatus(200)
            ->assertJson(['rut' => '76086428-5']);
    }

    public function test_request_con_rut_invalido_devuelve_422(): void
    {
        $response = $this->postJson('/_test/sii/validar-rut', [
            'rut' => '11111111-9',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('rut');
    }

    public function test_request_con_rut_malformado_devuelve_422(): void
    {
        $response = $this->postJson('/_test/sii/validar-rut', [
            'rut' => 'abc',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('rut');
    }

    public function test_mensaje_de_error_es_en_espanol(): void
    {
        $validator = Validator::make(
            ['rut' => 'rut_invalido_evidente'],
            ['rut' => [new RutChileno()]]
        );

        $this->assertTrue($validator->fails(), 'La regla deberia rechazar un RUT malformado.');

        $mensaje = $validator->errors()->first('rut');

        $this->assertStringContainsString('RUT', $mensaje);
        $this->assertStringContainsString('valido', strtolower($mensaje));
    }

    public function test_regla_rechaza_valores_no_string(): void
    {
        $validator = Validator::make(
            ['rut' => 12345678],
            ['rut' => [new RutChileno()]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('texto', strtolower($validator->errors()->first('rut')));
    }
}
