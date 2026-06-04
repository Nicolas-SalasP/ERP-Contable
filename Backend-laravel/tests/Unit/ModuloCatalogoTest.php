<?php

namespace Tests\Unit;

use App\Domains\Core\Support\ModuloPermisos;
use Tests\TestCase;

class ModuloCatalogoTest extends TestCase
{
    public function test_catalogo_cubre_todas_las_keys_del_map(): void
    {
        $catalogo = ModuloPermisos::catalogo();
        $keysCatalogo = array_column($catalogo, 'key');
        $keysMap = array_keys(ModuloPermisos::MAP);

        sort($keysCatalogo);
        sort($keysMap);
        $this->assertSame($keysMap, $keysCatalogo, 'El catalogo debe derivar de MAP sin omitir ni agregar keys');
    }

    public function test_cada_modulo_tiene_label_y_categoria(): void
    {
        foreach (ModuloPermisos::catalogo() as $modulo) {
            $this->assertArrayHasKey('label', $modulo);
            $this->assertArrayHasKey('categoria', $modulo);
            $this->assertNotEmpty($modulo['label']);
            $this->assertNotEmpty($modulo['categoria']);
        }
    }

    public function test_incluye_los_modulos_que_antes_faltaban_en_el_admin(): void
    {
        $keys = array_column(ModuloPermisos::catalogo(), 'key');

        foreach (['sii.certificado', 'sii.dte', 'inventario.picking', 'inventario.despachos', 'inventario.ubicaciones'] as $key) {
            $this->assertContains($key, $keys);
        }
    }
}
