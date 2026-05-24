<?php

namespace App\Domains\Sii\Console\Commands;

use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GenerarXmlPruebaCommand extends Command
{
    protected $signature = 'sii:generar-xml-prueba
                            {dte_id : ID de SiiDteEmitido}
                            {--out= : Path opcional para volcar el XML al disco}';

    protected $description = 'Genera el XML del DTE (sin firmar) y lo valida contra el XSD oficial.';

    public function handle(DteXmlBuilder $builder): int
    {
        $id = (int) $this->argument('dte_id');

        try {
            /** @var SiiDteEmitido $dte */
            $dte = SiiDteEmitido::with(['detalles', 'referencias', 'traslado.madera', 'impuestosAdicionales'])
                ->findOrFail($id);
        } catch (ModelNotFoundException) {
            $this->error("SiiDteEmitido con ID {$id} no encontrado.");
            return self::FAILURE;
        }

        try {
            $xml = $builder->build($dte);
        } catch (DteIncompletoException $e) {
            $this->error('DTE incompleto: ' . $e->getMessage());
            $this->line('Motivo: ' . $e->motivo);
            return self::FAILURE;
        } catch (DteXmlInvalidException $e) {
            $this->error('XML invalido contra XSD: ' . $e->getMessage());
            return self::FAILURE;
        }

        $out = $this->option('out');
        if ($out) {
            file_put_contents($out, $xml);
            $this->info("XML escrito a: {$out} (" . strlen($xml) . ' bytes)');
        } else {
            $this->line($xml);
        }

        return self::SUCCESS;
    }
}
