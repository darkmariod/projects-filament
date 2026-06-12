<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\RelationManagers\TechnicalCompositionRelationManager;
use App\Models\TechnicalComposition;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected array $manufacturerData = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Eliminar')
                ->visible(fn(): bool => auth()->user()?->can('delete', $this->getRecord()) ?? false),
        ];
    }

    protected function getSaveFormAction(): \Filament\Actions\Action
    {
        return parent::getSaveFormAction()->label('Guardar');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $tc = $this->record->technicalComposition;
        if ($tc) {
            $data['manufacturer']         = $tc->manufacturer;
            $data['manufacturer_ruc']     = $tc->manufacturer_ruc;
            $data['manufacturer_address'] = $tc->manufacturer_address;
            $data['manufacturing_country'] = $tc->manufacturing_country;
            $data['website']              = $tc->website;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->manufacturerData = [
            'manufacturer'         => $data['manufacturer'] ?? null,
            'manufacturer_ruc'     => $data['manufacturer_ruc'] ?? null,
            'manufacturer_address' => $data['manufacturer_address'] ?? null,
            'manufacturing_country' => $data['manufacturing_country'] ?? null,
            'website'              => $data['website'] ?? null,
        ];

        unset(
            $data['manufacturer'], $data['manufacturer_ruc'],
            $data['manufacturer_address'], $data['manufacturing_country'],
            $data['website']
        );

        return $data;
    }

    protected function afterSave(): void
    {
        if (empty(array_filter($this->manufacturerData))) {
            return;
        }

        $tc = $this->record->technicalComposition;
        if ($tc) {
            $tc->update($this->manufacturerData);
        } else {
            $this->record->technicalComposition()->create(
                array_merge($this->manufacturerData, ['active' => true])
            );
        }
    }

    public function getRelationManagers(): array
    {
        return [
            TechnicalCompositionRelationManager::class,
        ];
    }
}
