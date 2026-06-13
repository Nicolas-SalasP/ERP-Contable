<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Respaldo de servido de archivos del disco 'public' (logos de empresa).
 *
 * En produccion sin `php artisan storage:link`, el symlink public/storage no
 * existe y /storage/... caia a Laravel devolviendo 404 (logos rotos). La ruta
 * de respaldo en web.php sirve el archivo directo desde el disco public.
 */
class LogoStorageFallbackTest extends TestCase
{
    public function test_sirve_un_archivo_existente_del_disco_public(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('empresas/logos/demo.jpg', 'contenido-binario-falso');

        $response = $this->get('/storage/empresas/logos/demo.jpg');

        $response->assertOk();
        $this->assertSame('contenido-binario-falso', $response->streamedContent());
    }

    public function test_devuelve_404_si_el_archivo_no_existe(): void
    {
        Storage::fake('public');

        $this->get('/storage/empresas/logos/no-existe.jpg')->assertNotFound();
    }

    public function test_bloquea_path_traversal(): void
    {
        Storage::fake('public');

        $this->get('/storage/..%2f..%2f.env')->assertNotFound();
    }
}
