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

            // Backfill desde TC existente para productos creados antes de esta migración
            if (empty($data['commercial_name']))           $data['commercial_name']           = $tc->commercial_name;
            if (empty($data['product_family']))            $data['product_family']            = $tc->product_family;
            if (empty($data['springs']))                   $data['springs']                   = $tc->springs;
            if (empty($data['foam_description']))          $data['foam_description']          = $tc->foam_description;
            if (empty($data['conservation_instructions'])) $data['conservation_instructions'] = $tc->conservation_instructions;
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
        $hasManufacturerData = !empty(array_filter($this->manufacturerData));

        $productTcFields = [
            'commercial_name'           => $this->record->commercial_name,
            'product_family'            => $this->record->product_family,
            'springs'                   => $this->record->springs,
            'foam_description'          => $this->record->foam_description,
            'conservation_instructions' => $this->record->conservation_instructions,
        ];
        $hasProductTcData = !empty(array_filter($productTcFields));

        $tc = $this->record->technicalComposition;
        if ($tc) {
            $update = [];
            if ($hasManufacturerData) {
                $update = array_merge($update, $this->manufacturerData);
            }
            if ($hasProductTcData) {
                $update = array_merge($update, $productTcFields);
            }
            if (!empty($update)) {
                $tc->update($update);
            }
        } else {
            $template = TechnicalComposition::where('active', true)->first();

            if ($template) {
                $data = $template->replicate(['id', 'product_id', 'created_at', 'updated_at'])->toArray();
                if ($hasManufacturerData) {
                    $data = array_merge($data, $this->manufacturerData);
                }
                if ($hasProductTcData) {
                    $data = array_merge($data, $productTcFields);
                }
                $this->record->technicalComposition()->create($data);
            } elseif ($hasManufacturerData || $hasProductTcData) {
                $this->record->technicalComposition()->create(
                    array_merge($this->manufacturerData, $productTcFields, ['active' => true])
                );
            }
        }
    }

    public function getRelationManagers(): array
    {
        return [
            TechnicalCompositionRelationManager::class,
        ];
    }
}
