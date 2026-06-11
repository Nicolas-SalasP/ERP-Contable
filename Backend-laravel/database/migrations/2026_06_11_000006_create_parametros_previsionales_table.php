<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Parámetros legales versionados por período de vigencia.
        // NUNCA hardcodear en código — actualizar aquí cuando cambian las tasas/topes.
        // Fuentes: Superintendencia de Pensiones, SUSESO, Mintrab, Previred.
        Schema::create('parametros_previsionales', function (Blueprint $table) {
            $table->id();
            // Los parámetros son globales (no por empresa); empresa_id = null significa global.
            // Si alguna empresa tuviera reglas distintas (poco probable) se puede sobreescribir.
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();

            // AFP — Tasas por administradora (DL 3500)
            // Cotización obligatoria trabajador: 10% + comisión
            $table->decimal('afp_cotizacion_pct', 5, 4)->default(10.0000);  // 10%
            // Comisiones por AFP almacenadas como JSON: {"Capital": 1.44, "Habitat": 1.27, ...}
            $table->json('afp_comisiones_json');
            // SIS: aporte empleador (Ley 20.255) — varía por licitación
            $table->decimal('afp_sis_pct', 5, 4)->default(1.6200);  // 1.62% (desde abril 2026)

            // Tope imponible AFP y salud (en UF — se multiplica por UF del mes)
            $table->decimal('tope_imponible_uf', 8, 4)->default(90.0000);  // 90.0 UF (2026)

            // Salud (Ley 18.469)
            $table->decimal('salud_fonasa_pct', 5, 4)->default(7.0000);  // 7%

            // AFC — Seguro de Cesantía (Ley 19.728)
            $table->decimal('afc_indefinido_trabajador_pct', 5, 4)->default(0.6000);   // 0.6%
            $table->decimal('afc_indefinido_empleador_pct', 5, 4)->default(2.4000);    // 2.4%
            $table->decimal('afc_plazo_fijo_trabajador_pct', 5, 4)->default(0.0000);   // 0%
            $table->decimal('afc_plazo_fijo_empleador_pct', 5, 4)->default(3.0000);    // 3%
            $table->decimal('afc_tope_imponible_uf', 8, 4)->default(135.2000);        // 135.2 UF (2026)

            // Ingreso Mínimo Mensual (IMM) — para gratificación Art. 50 y asignación familiar
            $table->decimal('imm', 14, 2);

            // Gratificación Art. 50: tope = 4.75 × IMM / 12
            $table->decimal('gratificacion_tope_mensual_factor', 5, 4)->default(4.7500);

            // Cotización adicional empleador — Ley 21.735 (Reforma Previsional, gradual 2025-2033)
            // Ago2025-Jul2026: 1%, Ago2026-Jul2027: 2%, ..., desde Ago2033: 7%
            $table->decimal('cotizacion_adicional_empleador_pct', 5, 4)->default(1.0000);

            // Mutual (accidentes del trabajo) — Ley 16.744
            // El porcentaje varía por actividad económica; se configura por empresa si difiere
            $table->decimal('mutual_cotizacion_basica_pct', 5, 4)->default(0.9000);   // 0.9% básico

            // Tramos asignación familiar (JSON array de {hasta_pesos, monto_por_carga})
            $table->json('asignacion_familiar_tramos_json')->nullable();

            $table->text('fuente')->nullable();  // referencia oficial usada al cargar
            $table->timestamps();

            $table->index('vigente_desde');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_previsionales');
    }
};
