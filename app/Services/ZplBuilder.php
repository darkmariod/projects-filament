<?php

namespace App\Services;

/**
 * ZplBuilder — helper puro para generar strings ZPL.
 *
 * Encapsula la sintaxis ZPL (^XA, ^FO, ^FD, ^FS, etc.) en métodos
 * con nombre, eliminando la concatenación manual de strings.
 */
class ZplBuilder
{
    private string $zpl = '';

    public function __construct()
    {
        $this->zpl = '';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  HEADER / FOOTER
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Iniciar etiqueta con configuración base.
     */
    public function header(int $widthDots, int $heightDots): static
    {
        $this->zpl .= "^XA\n";
        $this->zpl .= "^PW{$widthDots}\n";
        $this->zpl .= "^LL{$heightDots}\n";
        $this->zpl .= "^MNW\n";      // non-continuous media
        $this->zpl .= "^MTT\n";      // thermal transfer
        $this->zpl .= "^MMT\n";      // tear-off
        $this->zpl .= "^PR4,4\n";    // 4 ips
        $this->zpl .= "^LH0,0\n";    // home
        $this->zpl .= "^CI28\n";     // UTF-8

        return $this;
    }

    /**
     * Cerrar etiqueta.
     */
    public function close(): string
    {
        return $this->zpl . "^XZ\n";
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  TEXT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Texto en una posición.
     */
    public function text(int $x, int $y, int $fontSize, string $value): static
    {
        $value = $this->escape($value);
        $this->zpl .= "^FO{$x},{$y}^A0N,{$fontSize},{$fontSize}^FD{$value}^FS\n";

        return $this;
    }

    /**
     * Texto con altura y ancho de fuente independientes.
     */
    public function textWH(int $x, int $y, int $height, int $width, string $value): static
    {
        $value = $this->escape($value);
        $this->zpl .= "^FO{$x},{$y}^A0N,{$height},{$width}^FD{$value}^FS\n";

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  BARCODE / QR
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Código de barras Code 128.
     */
    public function barcode128(int $x, int $y, int $height, string $code): static
    {
        $code = $this->escape($code);
        $this->zpl .= "^FO{$x},{$y}^BCN,{$height},N,N,N^FD{$code}^FS\n";

        return $this;
    }

    /**
     * Código QR.
     */
    public function qrCode(int $x, int $y, int $magnification, string $data): static
    {
        $data = $this->escape($data);
        $this->zpl .= "^FO{$x},{$y}^BQN,2,{$magnification}^FDQA,{$data}^FS\n";

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  LINES / SEPARATORS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Línea horizontal o vertical.
     */
    public function line(int $x, int $y, int $width, int $thickness): static
    {
        $this->zpl .= "^FO{$x},{$y}^GB{$width},{$thickness},{$thickness}^FS\n";

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  RAW
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Agregar ZPL crudo (para casos especiales).
     */
    public function raw(string $zpl): static
    {
        $this->zpl .= $zpl;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  INTERNAL
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Escapar caracteres de control ZPL.
     */
    private function escape(string $value): string
    {
        return str_replace(['^', '~', '\\'], ['^^', '~~', '\\\\'], $value);
    }
}
