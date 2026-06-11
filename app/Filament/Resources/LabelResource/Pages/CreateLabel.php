<?php

namespace App\Filament\Resources\LabelResource\Pages;

use App\Filament\Resources\LabelResource;
use App\Services\SerialGeneratorService;
use Filament\Resources\Pages\CreateRecord;

class CreateLabel extends CreateRecord
{
    protected static string $resource = LabelResource::class;

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()->label('Crear');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generar serial y sequence_number si están vacíos
        if (empty($data['serial']) && !empty($data['product_id'])) {
            $generated = app(SerialGeneratorService::class)->generateForProduct($data['product_id']);
            $data['serial']          = $generated['serial'];
            $data['sequence_number'] = $generated['sequence_number'];
        } elseif (empty($data['sequence_number']) && !empty($data['product_id'])) {
            // Si ya hay serial pero falta sequence_number, obtenerlo del producto
            $product = \App\Models\Product::find($data['product_id']);
            if ($product) {
                $service = app(SerialGeneratorService::class);
                $last = \App\Models\Label::where('serial', 'like', now()->format('ym') . '-' . strtoupper($product->product_code) . '-%')
                    ->orderBy('sequence_number', 'desc')
                    ->first();
                $data['sequence_number'] = $last ? ((int) $last->sequence_number) + 1 : 1;
            }
        }

        // Auto-generar barcode desde el producto si está vacío
        if (empty($data['barcode']) && !empty($data['product_id'])) {
            $product = \App\Models\Product::find($data['product_id']);
            if ($product && $product->barcode) {
                $data['barcode'] = $product->barcode;
            }
        }

        // Si no se proveyó qr_url, auto-generarla desde el serial
        if (empty($data['qr_url']) && !empty($data['serial'])) {
            $data['qr_url'] = app(SerialGeneratorService::class)->buildQrUrl($data['serial']);
        }

        return $data;
    }
}
