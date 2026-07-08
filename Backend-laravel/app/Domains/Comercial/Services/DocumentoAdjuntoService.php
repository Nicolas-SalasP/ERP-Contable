<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\DocumentoAdjunto;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\OrdenCompra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A diferencia del `archivo_pdf` original (un solo documento), permite varios adjuntos
 * (con imágenes recomprimidas) para Factura, Cotizacion u OrdenCompra.
 */
class DocumentoAdjuntoService
{
    private const DISCO = 'local';
    private const ANCHO_MAXIMO_IMAGEN = 1600;
    private const CALIDAD_JPEG = 75;

    /** Config por tipo de documento padre: columna FK, modelo, carpeta de storage y mensaje de error. */
    private const TIPOS = [
        'factura' => [
            'columna' => 'factura_id',
            'modelo' => Factura::class,
            'carpeta' => 'facturas/adjuntos',
            'mensaje' => 'La factura no existe.',
        ],
        'cotizacion' => [
            'columna' => 'cotizacion_id',
            'modelo' => Cotizacion::class,
            'carpeta' => 'cotizaciones/adjuntos',
            'mensaje' => 'La cotización no existe.',
        ],
        'orden_compra' => [
            'columna' => 'orden_compra_id',
            'modelo' => OrdenCompra::class,
            'carpeta' => 'ordenes-compra/adjuntos',
            'mensaje' => 'La orden de compra no existe.',
        ],
    ];

    public function listar(int $empresaId, int $facturaId)
    {
        return $this->listarPorTipo('factura', $empresaId, $facturaId);
    }

    public function listarCotizacion(int $empresaId, int $cotizacionId)
    {
        return $this->listarPorTipo('cotizacion', $empresaId, $cotizacionId);
    }

    public function listarOrdenCompra(int $empresaId, int $ordenCompraId)
    {
        return $this->listarPorTipo('orden_compra', $empresaId, $ordenCompraId);
    }

    /** @param  UploadedFile[]  $archivos
     * @return DocumentoAdjunto[] */
    public function subir(int $empresaId, int $usuarioId, int $facturaId, array $archivos): array
    {
        return $this->subirPorTipo('factura', $empresaId, $usuarioId, $facturaId, $archivos);
    }

    /** @param  UploadedFile[]  $archivos
     * @return DocumentoAdjunto[] */
    public function subirCotizacion(int $empresaId, int $usuarioId, int $cotizacionId, array $archivos): array
    {
        return $this->subirPorTipo('cotizacion', $empresaId, $usuarioId, $cotizacionId, $archivos);
    }

    /** @param  UploadedFile[]  $archivos
     * @return DocumentoAdjunto[] */
    public function subirOrdenCompra(int $empresaId, int $usuarioId, int $ordenCompraId, array $archivos): array
    {
        return $this->subirPorTipo('orden_compra', $empresaId, $usuarioId, $ordenCompraId, $archivos);
    }

    public function eliminar(int $empresaId, int $facturaId, int $adjuntoId): void
    {
        $this->eliminarPorTipo('factura', $empresaId, $facturaId, $adjuntoId);
    }

    public function eliminarCotizacion(int $empresaId, int $cotizacionId, int $adjuntoId): void
    {
        $this->eliminarPorTipo('cotizacion', $empresaId, $cotizacionId, $adjuntoId);
    }

    public function eliminarOrdenCompra(int $empresaId, int $ordenCompraId, int $adjuntoId): void
    {
        $this->eliminarPorTipo('orden_compra', $empresaId, $ordenCompraId, $adjuntoId);
    }

    public function obtenerParaDescarga(int $empresaId, int $facturaId, int $adjuntoId): DocumentoAdjunto
    {
        return $this->obtenerParaDescargaPorTipo('factura', $empresaId, $facturaId, $adjuntoId);
    }

    public function obtenerParaDescargaCotizacion(int $empresaId, int $cotizacionId, int $adjuntoId): DocumentoAdjunto
    {
        return $this->obtenerParaDescargaPorTipo('cotizacion', $empresaId, $cotizacionId, $adjuntoId);
    }

    public function obtenerParaDescargaOrdenCompra(int $empresaId, int $ordenCompraId, int $adjuntoId): DocumentoAdjunto
    {
        return $this->obtenerParaDescargaPorTipo('orden_compra', $empresaId, $ordenCompraId, $adjuntoId);
    }

    private function listarPorTipo(string $tipo, int $empresaId, int $padreId)
    {
        $this->obtenerPadre($tipo, $empresaId, $padreId);

        return DocumentoAdjunto::where('empresa_id', $empresaId)
            ->where(self::TIPOS[$tipo]['columna'], $padreId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /** @param  UploadedFile[]  $archivos
     * @return DocumentoAdjunto[] */
    private function subirPorTipo(string $tipo, int $empresaId, int $usuarioId, int $padreId, array $archivos): array
    {
        $this->obtenerPadre($tipo, $empresaId, $padreId);
        $columna = self::TIPOS[$tipo]['columna'];
        $carpeta = self::TIPOS[$tipo]['carpeta'];

        $creados = [];
        foreach ($archivos as $archivo) {
            $rutaGuardada = $archivo->store($carpeta . '/' . $padreId, self::DISCO);
            $rutaAbsoluta = Storage::disk(self::DISCO)->path($rutaGuardada);

            $mime = $archivo->getMimeType();
            if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $this->comprimirImagen($rutaAbsoluta, $mime);
            }

            $creados[] = DocumentoAdjunto::create([
                'empresa_id' => $empresaId,
                $columna => $padreId,
                'nombre_original' => substr($archivo->getClientOriginalName(), 0, 255),
                'ruta' => $rutaGuardada,
                'mime_type' => $mime,
                'tamano_bytes' => filesize($rutaAbsoluta),
                'usuario_id' => $usuarioId,
            ]);
        }

        return $creados;
    }

    private function eliminarPorTipo(string $tipo, int $empresaId, int $padreId, int $adjuntoId): void
    {
        $adjunto = DocumentoAdjunto::where('empresa_id', $empresaId)
            ->where(self::TIPOS[$tipo]['columna'], $padreId)
            ->find($adjuntoId);

        if (!$adjunto) {
            throw ComercialException::noEncontrado('El documento adjunto no existe.');
        }

        // Borra el archivo físico primero: si se borrara la fila antes y esto fallara, quedaría un adjunto "fantasma" sin referencia a la ruta.
        Storage::disk(self::DISCO)->delete($adjunto->ruta);
        $adjunto->delete();
    }

    private function obtenerParaDescargaPorTipo(string $tipo, int $empresaId, int $padreId, int $adjuntoId): DocumentoAdjunto
    {
        $adjunto = DocumentoAdjunto::where('empresa_id', $empresaId)
            ->where(self::TIPOS[$tipo]['columna'], $padreId)
            ->find($adjuntoId);

        if (!$adjunto || !Storage::disk(self::DISCO)->exists($adjunto->ruta)) {
            throw ComercialException::noEncontrado('El documento adjunto no existe.');
        }

        return $adjunto;
    }

    private function obtenerPadre(string $tipo, int $empresaId, int $padreId): Model
    {
        $config = self::TIPOS[$tipo];
        $modelo = $config['modelo'];

        /** @var Model|null $padre */
        $padre = $modelo::where('empresa_id', $empresaId)->find($padreId);

        if (!$padre) {
            throw ComercialException::noEncontrado($config['mensaje']);
        }

        return $padre;
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
