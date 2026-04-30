<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventario_unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 80);
            $table->boolean('permite_decimal')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('inventario_bodegas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('codigo', 20);
            $table->string('nombre', 120);
            $table->string('direccion', 255)->nullable();
            $table->enum('estado', ['ACTIVA', 'INACTIVA'])->default('ACTIVA');
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo']);
        });

        Schema::create('inventario_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('sku', 50);
            $table->string('nombre', 180);
            $table->text('descripcion')->nullable();
            $table->enum('tipo_producto', ['BIEN', 'SERVICIO', 'INSUMO'])->default('BIEN');
            $table->foreignId('unidad_medida_id')->constrained('inventario_unidades_medida');
            $table->enum('metodo_valorizacion', ['PMP', 'FIFO'])->default('PMP');
            $table->decimal('costo_promedio', 18, 4)->default(0);
            $table->decimal('precio_venta_neto', 18, 4)->default(0);
            $table->boolean('afecto_iva')->default(true);
            $table->string('codigo_barra', 80)->nullable();
            $table->decimal('stock_minimo', 18, 4)->default(0);
            $table->foreignId('bodega_defecto_id')->nullable()->constrained('inventario_bodegas')->nullOnDelete();
            $table->boolean('permite_merma')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'sku']);
            $table->index(['empresa_id', 'nombre']);
        });

        Schema::create('inventario_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('inventario_productos')->cascadeOnDelete();
            $table->foreignId('bodega_id')->constrained('inventario_bodegas')->cascadeOnDelete();
            $table->decimal('stock_actual', 18, 4)->default(0);
            $table->decimal('costo_promedio', 18, 4)->default(0);
            $table->decimal('valor_total', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['empresa_id', 'producto_id', 'bodega_id']);
        });

        DB::table('inventario_unidades_medida')->insert([
            [
                'codigo' => 'UN',
                'nombre' => 'Unidad',
                'permite_decimal' => false,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 'KG',
                'nombre' => 'Kilogramo',
                'permite_decimal' => true,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 'LT',
                'nombre' => 'Litro',
                'permite_decimal' => true,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 'M',
                'nombre' => 'Metro',
                'permite_decimal' => true,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 'M2',
                'nombre' => 'Metro cuadrado',
                'permite_decimal' => true,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 'M3',
                'nombre' => 'Metro cúbico',
                'permite_decimal' => true,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 'HR',
                'nombre' => 'Hora',
                'permite_decimal' => true,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 'CJ',
                'nombre' => 'Caja',
                'permite_decimal' => false,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->agregarPermisosARoles();
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_stock');
        Schema::dropIfExists('inventario_productos');
        Schema::dropIfExists('inventario_bodegas');
        Schema::dropIfExists('inventario_unidades_medida');
    }

    private function agregarPermisosARoles(): void
    {
        $permisosPorRol = [
            'Administrador' => [
                'inventario.productos.ver',
                'inventario.productos.crear',
                'inventario.productos.editar',
                'inventario.bodegas.ver',
                'inventario.bodegas.crear',
            ],
            'Contador' => [
                'inventario.productos.ver',
                'inventario.productos.crear',
                'inventario.productos.editar',
                'inventario.bodegas.ver',
                'inventario.bodegas.crear',
            ],
            'Auditor' => [
                'inventario.productos.ver',
                'inventario.bodegas.ver',
            ],
        ];

        foreach ($permisosPorRol as $nombreRol => $permisosNuevos) {
            $rol = DB::table('roles')->where('nombre', $nombreRol)->first();

            if (!$rol) {
                continue;
            }

            $permisosActuales = $rol->permisos ? json_decode($rol->permisos, true) : [];

            if (!is_array($permisosActuales)) {
                $permisosActuales = [];
            }

            DB::table('roles')->where('id', $rol->id)->update([
                'permisos' => json_encode(
                    array_values(
                        array_unique(
                            array_merge($permisosActuales, $permisosNuevos)
                        )
                    )
                ),
            ]);
        }
    }
};