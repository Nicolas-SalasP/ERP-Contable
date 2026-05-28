<?php

namespace App\Domains\Sii\Console\Commands;

use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Services\Caf\CafService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CargarCafCommand extends Command
{
    protected $signature = 'sii:cargar-caf {empresa_id : ID de la empresa} {ruta : Ruta absoluta al archivo XML del CAF}';

    protected $description = 'Carga un CAF desde un archivo XML para la empresa especificada.';

    public function handle(CafService $service): int
    {
        $empresaId = (int) $this->argument('empresa_id');
        $ruta      = (string) $this->argument('ruta');

        if (! is_file($ruta)) {
            $this->error("Archivo no encontrado: {$ruta}");

            return self::FAILURE;
        }

        $xml = file_get_contents($ruta);
        if ($xml === false || $xml === '') {
            $this->error("No se pudo leer el archivo: {$ruta}");

            return self::FAILURE;
        }

        try {
            $caf = $service->cargar($empresaId, $xml);
        } catch (CafInvalidoException $e) {
            $this->error('CAF invalido: ' . $e->getMessage());
            $this->line('Motivo: ' . $e->motivo);

            return self::FAILURE;
        } catch (ModelNotFoundException) {
            $this->error("Empresa no encontrada: {$empresaId}");

            return self::FAILURE;
        }

        $this->info('CAF cargado correctamente.');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['caf_id',             (string) $caf->id],
                ['empresa_id',         (string) $caf->empresa_id],
                ['tipo_dte',           (string) $caf->tipo_dte],
                ['rango',              "{$caf->folio_desde} - {$caf->folio_hasta}"],
                ['folio_actual',       (string) $caf->folio_actual],
                ['sii_idk',            $caf->sii_idk],
                ['rut_empresa',        $caf->rut_empresa_caf],
                ['fecha_autorizacion', $caf->fecha_autorizacion?->toDateString() ?? '—'],
                ['fecha_vencimiento',  $caf->fecha_vencimiento?->toDateString() ?? '—'],
                ['estado',             $caf->estado],
            ]
        );

        return self::SUCCESS;
    }
}
