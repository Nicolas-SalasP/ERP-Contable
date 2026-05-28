<?php

namespace Database\Factories\Sii;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Support\RutHelper;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends Factory<SiiCaf>
 */
class SiiCafFactory extends Factory
{
    protected $model = SiiCaf::class;

    public function definition(): array
    {
        $desde = 1;
        $hasta = 50;

        $pemDummy = "-----BEGIN RSA PRIVATE KEY-----\nDUMMY_KEY_F3_TESTS\n-----END RSA PRIVATE KEY-----";
        $xmlDummy = '<AUTORIZACION><CAF version="1.0"><DA><RE>76123456-7</RE></DA></CAF></AUTORIZACION>';

        $rutEmpresa = $this->rutValido();

        return [
            'empresa_id'           => fn () => $this->crearEmpresaStub($rutEmpresa)->id,
            'tipo_dte'             => 33,
            'folio_desde'          => $desde,
            'folio_hasta'          => $hasta,
            'folio_actual'         => $desde,
            'folios_usados'        => 0,
            'folios_huerfanos'     => 0,
            'fecha_autorizacion'   => now()->subDays(30)->toDateString(),
            'fecha_vencimiento'    => now()->addMonths(5)->toDateString(),
            'rut_empresa_caf'      => $rutEmpresa,
            'razon_social_caf'     => 'Empresa CAF Factory',
            'sii_idk'              => (string) random_int(100, 9_999_999),
            'rsa_sk_cifrada'       => Crypt::encryptString($pemDummy),
            'xml_completo_cifrado' => Crypt::encryptString($xmlDummy),
            'rsa_pubk'             => "-----BEGIN PUBLIC KEY-----\nDUMMY_PUBKEY\n-----END PUBLIC KEY-----",
            'firma_caf'            => 'FIRMA_CAF_DUMMY_BASE64==',
            'estado'               => SiiCaf::ESTADO_ACTIVO,
        ];
    }

    public function tipo33(): static
    {
        return $this->state(fn () => ['tipo_dte' => 33]);
    }

    public function tipo39(): static
    {
        return $this->state(fn () => ['tipo_dte' => 39]);
    }

    public function tipo52(): static
    {
        return $this->state(fn () => ['tipo_dte' => 52]);
    }

    public function rangoChico(): static
    {
        return $this->state(fn () => [
            'folio_desde'  => 1,
            'folio_hasta'  => 10,
            'folio_actual' => 1,
        ]);
    }

    public function rangoGrande(): static
    {
        return $this->state(fn () => [
            'folio_desde'  => 1,
            'folio_hasta'  => 1000,
            'folio_actual' => 1,
        ]);
    }

    public function agotado(): static
    {
        return $this->state(fn (array $attrs) => [
            'folio_actual' => ($attrs['folio_hasta'] ?? 50) + 1,
            'estado'       => SiiCaf::ESTADO_AGOTADO,
        ]);
    }

    public function revocado(): static
    {
        return $this->state(fn () => ['estado' => SiiCaf::ESTADO_REVOCADO]);
    }

    private function crearEmpresaStub(string $rut): Empresa
    {
        return Empresa::create([
            'rut'          => $rut,
            'razon_social' => 'Empresa Stub ' . uniqid(),
        ]);
    }

    private function rutValido(): string
    {
        $num = random_int(76_000_000, 99_999_999);

        return $num . '-' . RutHelper::calcularDv($num);
    }
}
