<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Convierte una imagen (PNG/JPG) en un bloque ZPL ^GFA para imprimir en la Zebra.
 *
 * Replica en PHP (GD) la lógica del script Python `logo2zpl.py`:
 *   - escala la imagen a un ancho fijo en dots
 *   - la aplana sobre blanco y la lleva a 1-bit por umbral
 *   - empaqueta los bits por fila y los emite como hex en un ^GFA
 *
 * El resultado se cachea en storage/app/zpl-cache/ y solo se regenera si cambia
 * el archivo de origen (por mtime), para no re-codificar en cada impresión.
 */
class ImageToZplService
{
    /** Caja objetivo del gráfico en dots (misma zona que el logo Paraíso). La imagen
     *  se escala para ENTRAR en esta caja manteniendo proporción, así el layout de la
     *  etiqueta no se corre aunque la imagen sea alta. */
    private const TARGET_WIDTH  = 320;
    private const MAX_HEIGHT     = 96;

    /** Umbral 0-255: por debajo = negro. */
    private const THRESHOLD = 200;

    private const CACHE_DIR = 'zpl-cache';

    /**
     * Devuelve el bloque ^GFA para la imagen en el disco público indicado,
     * o null si no existe / no se puede procesar.
     *
     * @param  string  $publicPath  Ruta relativa dentro del disco 'public' (ej. products/xxx.png)
     */
    public function forPublicImage(?string $publicPath): ?string
    {
        if (empty($publicPath)) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($publicPath)) {
            return null;
        }

        $absolute = $disk->path($publicPath);
        $cacheKey = md5($publicPath) . '-' . @filemtime($absolute) . '.gfa';
        $cachePath = self::CACHE_DIR . '/' . $cacheKey;

        $local = Storage::disk('local');
        if ($local->exists($cachePath)) {
            $cached = trim((string) $local->get($cachePath));
            return $cached !== '' ? $cached : null;
        }

        $gfa = $this->encode($absolute);

        if ($gfa !== null) {
            $local->put($cachePath, $gfa);
        }

        return $gfa;
    }

    /**
     * Codifica un archivo de imagen a un bloque ^GFA. Null si GD no puede abrirlo.
     */
    public function encode(string $absolutePath): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = @file_get_contents($absolutePath);
        if ($raw === false) {
            return null;
        }

        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($src);
            return null;
        }

        // Escalar para ENTRAR en la caja (ancho x alto máx) manteniendo proporción.
        $scale  = min(self::TARGET_WIDTH / $srcW, self::MAX_HEIGHT / $srcH);
        $width  = max(1, (int) round($srcW * $scale));
        $height = max(1, (int) round($srcH * $scale));

        // Lienzo blanco (aplana transparencia).
        $canvas = imagecreatetruecolor($width, $height);
        $white  = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $width, $height, $srcW, $srcH);
        imagedestroy($src);

        $bytesPerRow = (int) (($width + 7) / 8);
        $total       = $bytesPerRow * $height;
        $hex         = '';

        for ($y = 0; $y < $height; $y++) {
            $row = array_fill(0, $bytesPerRow, 0);

            for ($x = 0; $x < $width; $x++) {
                $rgb  = imagecolorat($canvas, $x, $y);
                $r    = ($rgb >> 16) & 0xFF;
                $g    = ($rgb >> 8) & 0xFF;
                $b    = $rgb & 0xFF;
                $gray = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);

                if ($gray < self::THRESHOLD) { // pixel negro
                    $row[intdiv($x, 8)] |= 0x80 >> ($x % 8);
                }
            }

            foreach ($row as $byte) {
                $hex .= str_pad(strtoupper(dechex($byte)), 2, '0', STR_PAD_LEFT);
            }
        }

        imagedestroy($canvas);

        return "^GFA,{$total},{$total},{$bytesPerRow},{$hex}";
    }
}
