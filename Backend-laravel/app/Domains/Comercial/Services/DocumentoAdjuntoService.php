<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Models\DocumentoAdjunto;
use App\Domains\Comercial\Models\Factura;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** A diferencia del `archivo_pdf` original (un solo documento), permite varios adjuntos por factura con imágenes recomprimidas. */
class DocumentoAdjuntoService
{
    private const DISCO = 'local';
    private const CARPETA = 'facturas/adjuntos';
    private const ANCHO_MAXIMO_IMAGEN = 1600;
    private const CALIDAD_JPEG = 75;

    public function listar(int $empresaId, int $facturaId)
    {
        $this->obtenerFactura($empresaId, $facturaId);

        return DocumentoAdjunto::where('empresa_id', $empresaId)
            ->where('factura_id', $facturaId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * @param  UploadedFile[]  $archivos
     * @return DocumentoAdjunto[]
     */
    public function subir(int $empresaId, int $usuarioId, int $facturaId, array $archivos): array
    {
        $this->obtenerFactura($empresaId, $facturaId);

        $creados = [];
        foreach ($archivos as $archivo) {
            $rutaGuardada = $archivo->store(self::CARPETA . '/' . $facturaId, self::DISCO);
            $rutaAbsoluta = Storage::disk(self::DISCO)->path($rutaGuardada);

            $mime = $archivo->getMimeType();
            if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $this->comprimirImagen($rutaAbsoluta, $mime);
            }

            $creados[] = DocumentoAdjunto::create([
                'empresa_id' => $empresaId,
                'factura_id' => $facturaId,
                'nombre_original' => substr($archivo->getClientOriginalName(), 0, 255),
                'ruta' => $rutaGuardada,
                'mime_type' => $mime,
                'tamano_bytes' => filesize($rutaAbsoluta),
                'usuario_id' => $usuarioId,
            ]);
        }

        return $creados;
    }

    public function eliminar(int $empresaId, int $facturaId, int $adjuntoId): void
    {
        $adjunto = DocumentoAdjunto::where('empresa_id', $empresaId)
            ->where('factura_id', $facturaId)
            ->find($adjuntoId);

        if (!$adjunto) {
            throw ComercialException::noEncontrado('El documento adjunto no existe.');
        }

        // Borra el archivo físico primero: si se borrara la fila antes y esto fallara, quedaría un adjunto "fantasma" sin referencia a la ruta.
        Storage::disk(self::DISCO)->delete($adjunto->ruta);
        $adjunto->delete();
    }

    public function obtenerParaDescarga(int $empresaId, int $facturaId, int $adjuntoId): DocumentoAdjunto
    {
        $adjunto = DocumentoAdjunto::where('empresa_id', $empresaId)
            ->where('factura_id', $facturaId)
            ->find($adjuntoId);

        if (!$adjunto || !Storage::disk(self::DISCO)->exists($adjunto->ruta)) {
            throw ComercialException::noEncontrado('El documento adjunto no existe.');
        }

        return $adjunto;
    }

    private function obtenerFactura(int $empresaId, int $facturaId): Factura
    {
        $factura = Factura::where('empresa_id', $empresaId)->find($facturaId);

        if (!$factura) {
            throw ComercialException::noEncontrado('La factura no existe.');
        }

        return $factura;
    }

    /** Best-effort: si GD no puede leer el archivo, se deja el original tal cual (las fotos de celular pesan varios MB sin necesidad). */
    private function comprimirImagen(string $rutaAbsoluta, string $mime): void
    {
        try {
            $imagen = match ($mime) {
                'image/jpeg' => imagecreatefromjpeg($rutaAbsoluta),
                'image/png' => imagecreatefrompng($rutaAbsoluta),
                'image/webp' => imagecreatefromwebp($rutaAbsoluta),
                default => false,
            };

            if ($imagen === false) {
                return;
            }

            $anchoOriginal = imagesx($imagen);
            $altoOriginal = imagesy($imagen);

            if ($anchoOriginal > self::ANCHO_MAXIMO_IMAGEN) {
                $nuevoAncho = self::ANCHO_MAXIMO_IMAGEN;
                $nuevoAlto = (int) round($altoOriginal * ($nuevoAncho / $anchoOriginal));

                $redimensionada = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
                if ($mime === 'image/png') {
                    imagealphablending($redimensionada, false);
                    imagesavealpha($redimensionada, true);
                }
                imagecopyresampled($redimensionada, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $anchoOriginal, $altoOriginal);
                imagedestroy($imagen);
                $imagen = $redimensionada;
            }

            match ($mime) {
                'image/jpeg' => imagejpeg($imagen, $rutaAbsoluta, self::CALIDAD_JPEG),
                'image/png' => imagepng($imagen, $rutaAbsoluta, 6),
                'image/webp' => imagewebp($imagen, $rutaAbsoluta, self::CALIDAD_JPEG),
                default => null,
            };

            imagedestroy($imagen);
        } catch (\Throwable $e) {
            // No bloquea la subida por un problema de compresión; el archivo original ya quedó guardado.
        }
    }
}
