<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\TechnicalComposition;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected array $manufacturerData = [];
    protected array $productTcFields = [];

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
        $this->productTcFields = [
            'commercial_name'           => $data['commercial_name'] ?? null,
            'product_family'            => $data['product_family'] ?? null,
            'springs'                   => $data['springs'] ?? null,
            'foam_description'          => $data['foam_description'] ?? null,
            'conservation_instructions' => $data['conservation_instructions'] ?? null,
        ];

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
        $hasManufacturerData = !empty(array_filter($this->manufacturerData));
        $hasProductTcData = !empty(array_filter($this->productTcFields));
        $template = TechnicalComposition::where('active', true)->first();

        if ($template) {
            $data = $template->replicate(['id', 'product_id', 'created_at', 'updated_at'])->toArray();
            if ($hasManufacturerData) {
                $data = array_merge($data, $this->manufacturerData);
            }
            if ($hasProductTcData) {
                $data = array_merge($data, $this->productTcFields);
            }
            $this->record->technicalComposition()->create($data);
        } elseif ($hasManufacturerData || $hasProductTcData) {
            $this->record->technicalComposition()->create(
                array_merge($this->manufacturerData, $this->productTcFields, ['active' => true])
            );
        }
    }
}
