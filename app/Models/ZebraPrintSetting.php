<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZebraPrintSetting extends Model
{
    protected $fillable = [
        'name',
        'connection_type',
        'printer_name',
        'printer_model',
        'dpi',
        'label_width_mm',
        'label_height_mm',
        'label_gap_mm',
        'width_dots',
        'height_dots',
        'margin_x',
        'margin_y',
        'qr_size',
        'barcode_height',
        'printer_ip',
        'printer_port',
        'chunk_size',
        'active',
        'show_logo',
    ];

    protected function casts(): array
    {
        return [
            'active'         => 'boolean',
            'show_logo'      => 'boolean',
            'dpi'            => 'integer',
            'width_dots'     => 'integer',
            'height_dots'    => 'integer',
            'margin_x'       => 'integer',
            'margin_y'       => 'integer',
            'qr_size'        => 'integer',
            'barcode_height' => 'integer',
            'printer_port'   => 'integer',
            'chunk_size'     => 'integer',
        ];
    }

    public function isNetworkConfigured(): bool
    {
        return $this->connection_type === 'network' && !empty($this->printer_ip);
    }

    public function isUsbConfigured(): bool
    {
        return $this->connection_type === 'usb' && !empty($this->printer_name);
    }

    public function getPrinterEndpoint(): string
    {
        if ($this->isUsbConfigured()) {
            return $this->printer_name . ' (USB)';
        }
        return "{$this->printer_ip}:{$this->printer_port}";
    }

    public function isAnyPrinterConfigured(): bool
    {
        return $this->isNetworkConfigured() || $this->isUsbConfigured();
    }
}
