<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\TechnicalComposition;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected array $manufacturerData = [];

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
        $this->manufacturerData = [
            'manufacturer'         => $data['manufacturer'] ?? null,
            'manufacturer_ruc'     => $data['manufacturer_ruc'] ?? null,
            'manufacturer_address' => $data['manufacturer_address'] ?? null,
            'manufacturing_country' => $data['manufacturing_country'] ?? null,
            'website'              => $data['website'] ?? null,
            'active'               => true,
        ];

        unset(
            $data['manufacturer'], $data['manufacturer_ruc'],
            $data['manufacturer_address'], $data['manufacturing_country'],
            $data['website']
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $template = TechnicalComposition::where('active', true)->first();
        $hasManufacturerData = !empty(array_filter($this->manufacturerData));

        if ($template) {
            $data = $template->replicate(['id', 'product_id', 'created_at', 'updated_at'])->toArray();
            if ($hasManufacturerData) {
                $data = array_merge($data, $this->manufacturerData);
            }
            $this->record->technicalComposition()->create($data);
        } elseif ($hasManufacturerData) {
            $this->record->technicalComposition()->create($this->manufacturerData);
        }
    }
}
