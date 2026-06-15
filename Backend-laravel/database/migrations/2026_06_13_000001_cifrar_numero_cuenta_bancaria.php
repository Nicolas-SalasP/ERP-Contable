<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Ampliar columna a text (temporalmente nullable para el backfill en DBs estrictas).
        Schema::table('cuentas_bancarias_empresa', function (Blueprint $table) {
            $table->text('numero_cuenta')->nullable()->change();
        });

        Schema::table('cuentas_bancarias_proveedores', function (Blueprint $table) {
            $table->text('numero_cuenta')->nullable()->change();
        });

        // 2. Backfill idempotente: cifrar solo los valores en texto plano.
        foreach (['cuentas_bancarias_empresa', 'cuentas_bancarias_proveedores'] as $tabla) {
            DB::table($tabla)->orderBy('id')->each(function ($fila) use ($tabla) {
                $valor = $fila->numero_cuenta;
                if ($valor === null) {
                    return;
                }

                // Si ya está cifrado, Crypt::decryptString tendrá éxito → omitir.
                try {
                    Crypt::decryptString($valor);
                    return; // ya cifrado
                } catch (\Throwable $e) {
                    // En claro → cifrar.
                }

                DB::table($tabla)->where('id', $fila->id)->update([
                    'numero_cuenta' => Crypt::encryptString($valor),
                ]);
            });
        }

        // 3. Restaurar NOT NULL (todos los valores ya están cifrados).
        Schema::table('cuentas_bancarias_empresa', function (Blueprint $table) {
            $table->text('numero_cuenta')->nullable(false)->change();
        });

        Schema::table('cuentas_bancarias_proveedores', function (Blueprint $table) {
            $table->text('numero_cuenta')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // 1. Descifrar best-effort antes de volver a string(50).
        foreach (['cuentas_bancarias_empresa', 'cuentas_bancarias_proveedores'] as $tabla) {
            DB::table($tabla)->orderBy('id')->each(function ($fila) use ($tabla) {
                $valor = $fila->numero_cuenta;
                if ($valor === null) {
                    return;
                }

                try {
                    $plain = Crypt::decryptString($valor);
                    DB::table($tabla)->where('id', $fila->id)->update([
                        'numero_cuenta' => $plain,
                    ]);
                } catch (\Throwable $e) {
                    // No se pudo descifrar; dejar como está.
                }
            });
        }

        // 2. Restaurar string(50).
        Schema::table('cuentas_bancarias_empresa', function (Blueprint $table) {
            $table->string('numero_cuenta', 50)->nullable(false)->change();
        });

        Schema::table('cuentas_bancarias_proveedores', function (Blueprint $table) {
            $table->string('numero_cuenta', 50)->nullable(false)->change();
        });
    }
};
