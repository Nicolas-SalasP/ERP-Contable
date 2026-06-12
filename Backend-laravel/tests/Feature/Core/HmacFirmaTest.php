<?php

namespace Tests\Feature\Core;

use App\Support\HmacFirma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * HMAC inter-servicio (Fase 1 compat). El endpoint /api/internal/web/sync-password
 * está protegido por VerifyWebApiKey: si la auth pasa devuelve 404 (usuario inexistente),
 * si falla devuelve 401. Por eso 404 = "autenticado", 401 = "rechazado".
 */
class HmacFirmaTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private const SECRETO = 'secreto-de-prueba-hmac';
    private const URL = '/api/internal/web/sync-password';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        config(['services.tenri_web.web_integration_key' => self::SECRETO]);
    }

    /** Construye los headers HMAC para un payload JSON dado. */
    private function firmar(array $payload, ?int $timestamp = null): array
    {
        $cuerpo = json_encode($payload);

        if ($timestamp === null) {
            return HmacFirma::headers(self::SECRETO, $cuerpo);
        }

        // Variante con timestamp explícito (para probar expiración).
        $nonce = bin2hex(random_bytes(8));
        $firma = hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . hash('sha256', $cuerpo), self::SECRETO);

        return ['X-Timestamp' => (string) $timestamp, 'X-Nonce' => $nonce, 'X-Signature' => $firma];
    }

    public function test_firma_y_verificacion_round_trip(): void
    {
        $cuerpo = json_encode(['a' => 1]);
        $headers = HmacFirma::headers(self::SECRETO, $cuerpo);

        $request = \Illuminate\Http\Request::create(self::URL, 'POST', [], [], [], [], $cuerpo);
        foreach ($headers as $k => $v) {
            $request->headers->set($k, $v);
        }

        $this->assertTrue(HmacFirma::verifica(self::SECRETO, $request));
        $this->assertFalse(HmacFirma::verifica('otro-secreto', $request));
    }

    public function test_request_firmada_con_hmac_pasa_el_middleware(): void
    {
        $payload = ['tenri_user_id' => 123456, 'password_hash' => bcrypt('x')];

        $this->json('POST', self::URL, $payload, $this->firmar($payload))
            ->assertStatus(404); // auth OK -> el controller no encuentra al usuario
    }

    public function test_legacy_key_rechazada_por_defecto(): void
    {
        // S-6: sin el flag de compatibilidad, la llave estática X-WEB-API-KEY no
        // autentica aunque el valor sea correcto. Solo se acepta firma HMAC.
        $payload = ['tenri_user_id' => 123456, 'password_hash' => bcrypt('x')];

        $this->json('POST', self::URL, $payload, ['X-WEB-API-KEY' => self::SECRETO])
            ->assertStatus(401);
    }

    public function test_legacy_key_aceptada_solo_con_flag_de_compat(): void
    {
        // Durante una migración coordinada el flag puede reactivar la llave legacy.
        config(['services.tenri_web.allow_legacy_key' => true]);

        $payload = ['tenri_user_id' => 123456, 'password_hash' => bcrypt('x')];

        $this->json('POST', self::URL, $payload, ['X-WEB-API-KEY' => self::SECRETO])
            ->assertStatus(404); // auth OK (flag activo) -> usuario inexistente
    }

    public function test_firma_invalida_es_rechazada(): void
    {
        $payload = ['tenri_user_id' => 1, 'password_hash' => 'x'];
        $headers = $this->firmar($payload);
        $headers['X-Signature'] = str_repeat('0', 64);

        $this->json('POST', self::URL, $payload, $headers)->assertStatus(401);
    }

    public function test_timestamp_expirado_es_rechazado(): void
    {
        $payload = ['tenri_user_id' => 1, 'password_hash' => 'x'];
        $headers = $this->firmar($payload, time() - (HmacFirma::VENTANA_SEGUNDOS + 60));

        $this->json('POST', self::URL, $payload, $headers)->assertStatus(401);
    }

    public function test_replay_del_mismo_nonce_es_rechazado(): void
    {
        $payload = ['tenri_user_id' => 123456, 'password_hash' => bcrypt('x')];
        $headers = $this->firmar($payload);

        $this->json('POST', self::URL, $payload, $headers)->assertStatus(404); // primera vez: OK
        $this->json('POST', self::URL, $payload, $headers)->assertStatus(401); // replay: rechazado
    }

    public function test_sin_credenciales_es_rechazado(): void
    {
        $this->json('POST', self::URL, ['tenri_user_id' => 1, 'password_hash' => 'x'])
            ->assertStatus(401);
    }
}
