<?php

declare(strict_types=1);

namespace App\Services;

/**
 * ImageToZplConverter — convierte imágenes PNG/JPG/WEBP a ^GFA ZPL monochrome.
 *
 * Usa GD para: detectar formato → redimensionar a max 320×120 → umbral de luminancia →
 * generar datos hex por fila → envolver en ^GFA. Resultado se cachea en storage/app/zpl-cache/.
 */
class ImageToZplConverter
{
    private const CACHE_DIR  = 'app/zpl-cache';
    private const MAX_WIDTH  = 320;
    private const MAX_HEIGHT = 120;
    private const THRESHOLD  = 128;

    /**
     * Convierte una imagen en un string ^GFA ZPL.
     * Retorna null si el archivo no existe, el formato no es soportado, o GD falla.
     */
    public function convert(string $relativePath): ?string
    {
        // ── Cache check ────────────────────────────────────────────────
        $cacheFile = $this->cachePath($relativePath);
        if (is_file($cacheFile)) {
            return file_get_contents($cacheFile) ?: null;
        }

        // ── Load image via GD ──────────────────────────────────────────
        $absolutePath = $this->resolvePath($relativePath);
        if ($absolutePath === null || !is_file($absolutePath)) {
            return null;
        }

        $image = $this->loadImage($absolutePath);
        if ($image === null) {
            return null;
        }

        // ── Resize to fit label area ───────────────────────────────────
        $resized = $this->resize($image, self::MAX_WIDTH, self::MAX_HEIGHT);
        imagedestroy($image);
        $image = $resized;

        // ── Monochrome conversion ──────────────────────────────────────
        $width  = imagesx($image);
        $height = imagesy($image);
        $hexData = $this->toMonochrome($image, $width, $height);
        imagedestroy($image);

        // ── Build ^GFA ────────────────────────────────────────────────
        $zpl = $this->buildZplGfa($width, $height, $hexData);

        // ── Write cache ────────────────────────────────────────────────
        $cacheDir = storage_path(self::CACHE_DIR);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($cacheFile, $zpl);

        return $zpl;
    }

    /**
     * Resuelve la ruta absoluta desde un path relativo.
     * Acepta paths que empiezan con 'storage/app/public/' o directos.
     */
    private function resolvePath(string $relativePath): ?string
    {
        // Normalize: strip leading 'storage/app/public/' if present
        $relativePath = ltrim($relativePath, '/');
        $relativePath = preg_replace('#^storage/app/public/#', '', $relativePath);

        $absolutePath = storage_path('app/public/' . $relativePath);
        if (is_file($absolutePath)) {
            return $absolutePath;
        }

        // Also try storage_path('app/' . ...) for non-public storage
        $absolutePath = storage_path('app/' . $relativePath);
        if (is_file($absolutePath)) {
            return $absolutePath;
        }

        return null;
    }

    /**
     * Carga una imagen desde disco usando GD.
     * Retorna \GdImage o null si el formato no es soportado.
     */
    private function loadImage(string $absolutePath): ?\GdImage
    {
        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return null;
        }

        $mime = $info['mime'];

        return match ($mime) {
            'image/png'  => @imagecreatefrompng($absolutePath),
            'image/jpeg' => @imagecreatefromjpeg($absolutePath),
            'image/webp' => @imagecreatefromwebp($absolutePath),
            default      => null,
        };
    }

    /**
     * Redimensiona manteniendo relación de aspecto.
     */
    private function resize(\GdImage $image, int $maxW, int $maxH): \GdImage
    {
        $origW = imagesx($image);
        $origH = imagesy($image);

        if ($origW <= $maxW && $origH <= $maxH) {
            return $image;
        }

        $ratio  = min($maxW / $origW, $maxH / $origH);
        $newW   = (int) round($origW * $ratio);
        $newH   = (int) round($origH * $ratio);

        $resized = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        return $resized;
    }

    /**
     * Convierte imagen a datos hex monochrome (1=blanco, 0=negro).
     * Usa umbral de luminancia: 0.299R + 0.587G + 0.114B > THRESHOLD → pixel blanco.
     * Retorna string hex continuo, fila por fila.
     */
    private function toMonochrome(\GdImage $image, int $width, int $height): string
    {
        $rowBytes = (int) ceil($width / 8);
        $hex = '';

        for ($y = 0; $y < $height; $y++) {
            $rowBits = '';
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $luminance = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                // In ZPL ^GFA: 1=white (no print), 0=black (print)
                // So luminance > threshold → 1 (white), else 0 (black)
                $rowBits .= $luminance > self::THRESHOLD ? '1' : '0';
            }

            // Pad to full byte boundary
            $remainder = strlen($rowBits) % 8;
            if ($remainder !== 0) {
                $rowBits .= str_repeat('1', 8 - $remainder);
            }

            // Convert 8-bit groups to hex. Cada byte SIEMPRE ocupa dos digitos:
            // dechex(0) devuelve "0", y sin el relleno los bytes menores a 16
            // aportan un solo caracter y desplazan toda la imagen.
            for ($i = 0; $i < strlen($rowBits); $i += 8) {
                $byte = substr($rowBits, $i, 8);
                $hex .= str_pad(dechex(bindec($byte)), 2, '0', STR_PAD_LEFT);
            }
        }

        return $hex;
    }

    /**
     * Construye el string ^GFA ZPL completo.
     */
    private function buildZplGfa(int $width, int $height, string $hexData): string
    {
        $rowBytes   = (int) ceil($width / 8);
        $totalBytes = $rowBytes * $height;

        // El tercer parametro de ^GFA es bytes POR FILA, no la altura. Enviar la
        // altura hace que la impresora reinterprete el ancho del grafico: una
        // imagen de 288x120 se dibuja como 960x36 y se sale de la etiqueta.
        return "^GFA,{$totalBytes},{$totalBytes},{$rowBytes},{$hexData}";
    }

    /**
     * Ruta al archivo de cache (md5 del path relativo).
     */
    private function cachePath(string $relativePath): string
    {
        $hash = md5($relativePath);

        return storage_path(self::CACHE_DIR . "/{$hash}.gfa");
    }
}
