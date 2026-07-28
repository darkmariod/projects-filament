<?php

namespace App\Filament\Resources\LabelBatchResource\Pages;

use App\Filament\Resources\LabelBatchResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLabelBatch extends EditRecord
{
    protected static string $resource = LabelBatchResource::class;

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
        $product = Product::with('technicalComposition', 'productModel')->find($data['product_id']);
        if (!$product) {
            return $data;
        }

        $parts = [
            "Código: {$product->product_code}",
            "Medidas: {$product->measurements_text}",
        ];

        if ($product->productModel) {
            $parts[] = "Modelo: {$product->productModel->name}";
        }

        if ($product->technicalComposition) {
            $tc = $product->technicalComposition;
            if ($tc->commercial_name) {
                $parts[] = "Nombre comercial: {$tc->commercial_name}";
            }
            if ($tc->cover_material) {
                $parts[] = "Tapiz: {$tc->cover_material}";
            }
            if ($tc->foam_description) {
                $parts[] = "Espuma: {$tc->foam_description}";
            }
        }

        $data['product_info_display'] = implode(' | ', $parts);
        $data['product_measurements_display'] = $product->measurements_text ?: '—';

        $tc = $product->technicalComposition;
        $model = $product->productModel;
        if ($tc) {
            $data['composition_detail_display'] = implode(' | ', array_filter([
                $tc->product_family ? "Familia: {$tc->product_family}" : null,
                $tc->springs ? "Resortes: {$tc->springs}" : null,
                $model?->warranty_years ? "Tiempo de garantía: {$model->warranty_years} años" : null,
                $tc->general_composition ? "Composición: {$tc->general_composition}" : null,
            ]));
        } else {
            $data['composition_detail_display'] = 'Sin datos de composición técnica';
        }

        return $data;
    }
}
