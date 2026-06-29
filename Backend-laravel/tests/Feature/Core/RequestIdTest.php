<?php

namespace Tests\Feature\Core;

use Tests\TestCase;

class RequestIdTest extends TestCase
{
    public function test_response_incluye_x_request_id_generado(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('X-Request-ID');
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
    }

    public function test_response_preserva_x_request_id_del_cliente(): void
    {
        $idCliente = 'mi-id-123';

        $response = $this->getJson('/api/health', ['X-Request-ID' => $idCliente]);

        $response->assertHeader('X-Request-ID', $idCliente);
    }

    public function test_request_id_es_uuid_cuando_no_viene_en_cabecera(): void
    {
        $response = $this->getJson('/api/health');

        $requestId = $response->headers->get('X-Request-ID');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $requestId,
        );
    }
}
