<?php

namespace Tests\Feature\Alertas;

use App\Domains\Alertas\Jobs\MotorAlertasJob;
use App\Domains\Alertas\Models\Alerta;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Models\PeriodoContable;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Tests del motor de alertas: genera la alerta correcta por tipo, respeta multitenancy
 * (una empresa no ve alertas de otra) y el lock atomico evita duplicados en ejecuciones
 * concurrentes (mismo patron que MonitorearCertificadosCommandTest, dominio Sii).
 */
class MotorAlertasJobTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        NotificationFacade::fake();
    }

    private function crearFacturaVencida(int $empresaId, string $tipo, float $montoBruto, int $diasVencido): Factura
    {
        $neto = round($montoBruto / 1.19, 2);
        $iva = round($montoBruto - $neto, 2);

        $datos = [
            'empresa_id' => $empresaId,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'numero_factura' => 'F-'.uniqid(),
            'tipo' => $tipo,
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now()->subDays($diasVencido + 30)->toDateString(),
            'fecha_vencimiento' => now()->subDays($diasVencido)->toDateString(),
            'monto_neto' => $neto,
            'monto_iva' => $iva,
            'monto_bruto' => $montoBruto,
            'estado' => 'REGISTRADA',
        ];

        if ($tipo === 'VENTA') {
            $cliente = Cliente::withoutGlobalScopes()->create([
                'empresa_id' => $empresaId,
                'rut' => rand(10000000, 99999999).'-'.rand(0, 9),
                'razon_social' => 'Cliente Test '.uniqid(),
                'estado' => 'ACTIVO',
            ]);
            $datos['cliente_id'] = $cliente->id;
        } else {
            $proveedor = Proveedor::withoutGlobalScopes()->create([
                'empresa_id' => $empresaId,
                'codigo_interno' => 'PROV-'.uniqid(),
                'rut' => rand(10000000, 99999999).'-'.rand(0, 9),
                'razon_social' => 'Proveedor Test '.uniqid(),
                'pais_iso' => 'CL',
                'moneda_defecto' => 'CLP',
            ]);
            $datos['proveedor_id'] = $proveedor->id;
        }

        return Factura::withoutGlobalScopes()->create($datos);
    }

    // ------------------------------------------------------------------
    // CxC vencida
    // ------------------------------------------------------------------

    public function test_genera_alerta_cxc_vencida_para_factura_de_venta_vencida(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin(['email' => 'finanzas@empresa.test']);
        $this->crearFacturaVencida($empresa->id, 'VENTA', 500000, 45);

        dispatch_sync(new MotorAlertasJob);

        $alerta = Alerta::withoutGlobalScopes()->where('tipo', 'cxc_vencida')->first();

        $this->assertNotNull($alerta);
        $this->assertSame($empresa->id, $alerta->empresa_id);
        $this->assertSame(Alerta::ESTADO_ENVIADA, $alerta->estado);
        $this->assertSame(Alerta::NIVEL_ADVERTENCIA, $alerta->nivel);
    }

    public function test_no_genera_alerta_cxc_para_factura_al_dia(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin(['email' => 'finanzas@empresa.test']);
        $this->crearFacturaVencida($empresa->id, 'VENTA', 500000, -10);

        dispatch_sync(new MotorAlertasJob);

        $this->assertSame(0, Alerta::withoutGlobalScopes()->where('tipo', 'cxc_vencida')->count());
    }

    public function test_cxp_vencida_critica_para_factura_de_compra_muy_atrasada(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin(['email' => 'finanzas@empresa.test']);
        $this->crearFacturaVencida($empresa->id, 'COMPRA', 200000, 90);

        dispatch_sync(new MotorAlertasJob);

        $alerta = Alerta::withoutGlobalScopes()->where('tipo', 'cxp_vencida')->first();

        $this->assertNotNull($alerta);
        $this->assertSame(Alerta::NIVEL_CRITICO, $alerta->nivel);
    }

    // ------------------------------------------------------------------
    // Periodo sin cerrar
    // ------------------------------------------------------------------

    public function test_genera_alerta_periodo_sin_cerrar_cuando_no_existe_cierre(): void
    {
        // El evaluador solo considera "vencido" el cierre pasados 20 dias del mes siguiente;
        // se fija la fecha para garantizar que el plazo ya expiro sin importar cuando corra el test.
        Carbon::setTestNow(Carbon::create(2026, 8, 25));

        [$empresa] = $this->crearEmpresaConAdmin(['email' => 'contador@empresa.test']);

        dispatch_sync(new MotorAlertasJob);

        $alerta = Alerta::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->where('tipo', 'periodo_sin_cerrar')
            ->first();

        $this->assertNotNull($alerta);

        Carbon::setTestNow();
    }

    public function test_no_genera_alerta_periodo_sin_cerrar_cuando_ya_esta_cerrado(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 25));

        [$empresa] = $this->crearEmpresaConAdmin(['email' => 'contador@empresa.test']);

        $periodoAnterior = now()->copy()->startOfMonth()->subMonthNoOverflow();

        PeriodoContable::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'anio' => $periodoAnterior->year,
            'mes' => $periodoAnterior->month,
            'estado' => PeriodoContable::ESTADO_CERRADO,
        ]);

        dispatch_sync(new MotorAlertasJob);

        $this->assertSame(
            0,
            Alerta::withoutGlobalScopes()
                ->where('empresa_id', $empresa->id)
                ->where('tipo', 'periodo_sin_cerrar')
                ->count()
        );

        Carbon::setTestNow();
    }

    // ------------------------------------------------------------------
    // Contrato RRHH por vencer
    // ------------------------------------------------------------------

    public function test_genera_alerta_contrato_por_vencer(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin(['email' => 'rrhh@empresa.test']);

        $empleado = Empleado::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'rut' => rand(10000000, 99999999).'-'.rand(0, 9),
            'nombres' => 'Juan',
            'apellido_paterno' => 'Perez',
            'fecha_nacimiento' => '1990-01-01',
        ]);

        Contrato::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado->id,
            'tipo' => 'PLAZO_FIJO',
            'fecha_inicio' => now()->subMonths(3)->toDateString(),
            'fecha_termino' => now()->addDays(5)->toDateString(),
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
            'sueldo_base' => 800000,
        ]);

        dispatch_sync(new MotorAlertasJob);

        $alerta = Alerta::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->where('tipo', 'contrato_por_vencer')
            ->first();

        $this->assertNotNull($alerta);
        $this->assertSame(Alerta::NIVEL_CRITICO, $alerta->nivel);
    }

    public function test_no_genera_alerta_para_contrato_indefinido(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin(['email' => 'rrhh@empresa.test']);

        $empleado = Empleado::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'rut' => rand(10000000, 99999999).'-'.rand(0, 9),
            'nombres' => 'Maria',
            'apellido_paterno' => 'Soto',
            'fecha_nacimiento' => '1990-01-01',
        ]);

        Contrato::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => now()->subYear()->toDateString(),
            'fecha_termino' => null,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
            'sueldo_base' => 800000,
        ]);

        dispatch_sync(new MotorAlertasJob);

        $this->assertSame(
            0,
            Alerta::withoutGlobalScopes()
                ->where('empresa_id', $empresa->id)
                ->where('tipo', 'contrato_por_vencer')
                ->count()
        );
    }

    // ------------------------------------------------------------------
    // Multitenancy: una empresa no debe generar/ver alertas de otra
    // ------------------------------------------------------------------

    public function test_aislamiento_multitenant_entre_empresas(): void
    {
        [$empresaA] = $this->crearEmpresaConAdmin(['email' => 'a@empresa.test']);
        [$empresaB, $usuarioB] = $this->crearEmpresaConAdmin(['email' => 'b@empresa.test']);

        $this->crearFacturaVencida($empresaA->id, 'VENTA', 900000, 45);

        dispatch_sync(new MotorAlertasJob);

        $alertaEmpresaA = Alerta::withoutGlobalScopes()
            ->where('empresa_id', $empresaA->id)
            ->where('tipo', 'cxc_vencida')
            ->first();
        $this->assertNotNull($alertaEmpresaA);

        // El usuario de la empresa B consulta el endpoint HTTP: EmpresaScope debe filtrar y no
        // debe ver la alerta de cxc_vencida generada para la empresa A.
        $response = $this->actingAs($usuarioB)->getJson('/api/alertas');
        $response->assertStatus(200);

        $tipos = collect($response->json('data.data'))->pluck('tipo');
        $this->assertNotContains('cxc_vencida', $tipos);
    }

    // ------------------------------------------------------------------
    // Lock atomico: evita duplicar notificaciones en ejecuciones concurrentes
    // ------------------------------------------------------------------

    public function test_no_duplica_alerta_al_ejecutar_el_job_dos_veces_seguidas(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin(['email' => 'finanzas@empresa.test']);
        $this->crearFacturaVencida($empresa->id, 'VENTA', 500000, 45);

        dispatch_sync(new MotorAlertasJob);
        dispatch_sync(new MotorAlertasJob);

        $this->assertSame(
            1,
            Alerta::withoutGlobalScopes()
                ->where('empresa_id', $empresa->id)
                ->where('tipo', 'cxc_vencida')
                ->count()
        );
    }

    public function test_lock_atomico_bloquea_procesamiento_concurrente(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin(['email' => 'finanzas@empresa.test']);
        $factura = $this->crearFacturaVencida($empresa->id, 'VENTA', 500000, 45);

        // Simula que otro worker ya tomo el lock para este candidato exacto antes de que
        // corra este job: la clave replica MotorAlertasJob::lockKey() para cxc_vencida/advertencia.
        $lockKey = sprintf(
            'alertas:notificar:%d:cxc_vencida:%s:%d:advertencia:unico',
            $empresa->id,
            Factura::class,
            $factura->id
        );

        Cache::put($lockKey, true, 30);

        dispatch_sync(new MotorAlertasJob);

        $this->assertSame(
            0,
            Alerta::withoutGlobalScopes()
                ->where('empresa_id', $empresa->id)
                ->where('tipo', 'cxc_vencida')
                ->count()
        );
    }
}
