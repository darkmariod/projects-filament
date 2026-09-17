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

        // El código de barras usa el SERIAL ÚNICO de cada etiqueta. Antes se copiaba
        // $product->barcode, que era el mismo para todas las etiquetas del producto
        // (código duplicado). Cada colchón debe tener su código único para validar.
        if (empty($data['barcode']) && !empty($data['serial'])) {
            $data['barcode'] = $data['serial'];
        }

        $service = app(SerialGeneratorService::class);

        // Generar token público no adivinable si falta
        if (empty($data['public_token'])) {
            $data['public_token'] = $service->generatePublicToken();
        }

        // Si no se proveyó qr_url, auto-generarla desde el token (nuevo esquema seguro)
        if (empty($data['qr_url']) && !empty($data['public_token'])) {
            $data['qr_url'] = $service->buildPublicUrl($data['public_token']);
        }

        return $data;
    }
}
