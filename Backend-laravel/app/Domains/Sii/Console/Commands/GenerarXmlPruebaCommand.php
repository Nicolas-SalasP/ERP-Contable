<?php

namespace App\Domains\Sii\Console\Commands;

use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Exceptions\CertificadoInvalidoException;
use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Xml\DteSigner;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\SetDte\SetDteBuilder;
use App\Domains\Sii\Services\Xml\SetDte\SetDteSigner;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;

class GenerarXmlPruebaCommand extends Command
{
    protected $signature = 'sii:generar-xml-prueba
                            {dte_id : ID de SiiDteEmitido}
                            {--out= : Path opcional para volcar el XML al disco}
                            {--caf= : ID opcional de SiiCaf para inyectar TED firmado}
                            {--firmar-documento : Firma <Documento> con XMLDSig usando el certificado activo de la empresa (requiere --caf)}
                            {--envolver-en-setdte : Envuelve el DTE firmado en <EnvioDTE><SetDTE> y firma el SetDTE (requiere --firmar-documento)}';

    protected $description = 'Genera el XML del DTE y lo valida contra el XSD oficial. Flags: --caf TED firmado RSA-SHA1; --firmar-documento XMLDSig sobre Documento; --envolver-en-setdte EnvioDTE con SetDTE firmado.';

    public function handle(
        DteXmlBuilder $builder,
        DteSigner $dteSigner,
        SetDteBuilder $setBuilder,
        SetDteSigner $setSigner
    ): int {
        $id = (int) $this->argument('dte_id');

        try {
            /** @var SiiDteEmitido $dte */
            $dte = SiiDteEmitido::with(['detalles', 'referencias', 'traslado.madera', 'impuestosAdicionales'])
                ->findOrFail($id);
        } catch (ModelNotFoundException) {
            $this->error("SiiDteEmitido con ID {$id} no encontrado.");
            return self::FAILURE;
        }

        $firmarDocumento  = (bool) $this->option('firmar-documento');
        $envolverEnSetdte = (bool) $this->option('envolver-en-setdte');

        $caf = null;
        $cafOption = $this->option('caf');
        if ($cafOption !== null && $cafOption !== '') {
            try {
                $caf = SiiCaf::findOrFail((int) $cafOption);
            } catch (ModelNotFoundException) {
                $this->error("SiiCaf con ID {$cafOption} no encontrado.");
                return self::FAILURE;
            }
        }

        if (($firmarDocumento || $envolverEnSetdte) && $caf === null) {
            $this->error('--firmar-documento y --envolver-en-setdte requieren --caf.');
            return self::FAILURE;
        }

        if ($envolverEnSetdte && ! $firmarDocumento) {
            $this->error('--envolver-en-setdte requiere --firmar-documento.');
            return self::FAILURE;
        }

        try {
            $xml = $builder->build($dte, $caf);

            if ($firmarDocumento) {
                $xml = $dteSigner->firmar($xml, $dte->empresa);
            }

            if ($envolverEnSetdte) {
                $setSinFirma = $setBuilder->build($dte->empresa, [['dte' => $dte, 'xml' => $xml]]);
                $xml         = $setSigner->firmar($setSinFirma, $dte->empresa);
            }
        } catch (DteIncompletoException $e) {
            $this->error('DTE incompleto: ' . $e->getMessage());
            $this->line('Motivo: ' . $e->motivo);
            return self::FAILURE;
        } catch (DteXmlInvalidException $e) {
            $this->error('XML invalido contra XSD: ' . $e->getMessage());
            return self::FAILURE;
        } catch (CafInvalidoException $e) {
            $this->error('CAF invalido: ' . $e->getMessage());
            $this->line('Motivo: ' . $e->motivo);
            return self::FAILURE;
        } catch (CertificadoInvalidoException $e) {
            $this->error('Certificado invalido: ' . $e->getMessage());
            $this->line('Motivo: ' . $e->motivo);
            return self::FAILURE;
        } catch (LogicException $e) {
            $this->error('Inconsistencia: ' . $e->getMessage());
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
