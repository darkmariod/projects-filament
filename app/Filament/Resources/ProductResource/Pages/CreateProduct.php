<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Category;
use App\Models\LabelBatch;
use App\Models\ProductModel;
use App\Models\TechnicalComposition;
use App\Services\SerialGeneratorService;
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
        // ── Resolver o crear Categoría ────────────────────────────────
        $category = Category::firstOrCreate(
            ['code' => $data['category_code']],
            ['name' => $data['category_name'], 'active' => true],
        );

        // ── Resolver o crear Modelo ────────────────────────────────────
        $model = ProductModel::firstOrCreate(
            ['code' => $data['model_code']],
            [
                'category_id'    => $category->id,
                'name'           => $data['model_name'],
                'type'           => $data['model_type'] ?? null,
                'class'          => $data['model_class'] ?? null,
                'warranty_years' => $data['model_warranty_years'] ?? 1,
                'active'         => true,
            ],
        );

        $data['product_model_id'] = $model->id;

        // Limpiar campos virtuales de categoría/modelo
        unset(
            $data['category_name'], $data['category_code'],
            $data['model_name'], $data['model_code'],
            $data['model_type'], $data['model_class'], $data['model_warranty_years'],
        );

        // ── Datos para TechnicalComposition ────────────────────────────
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

        // Auto-fill warranty_years from ProductModel into support_material ("Tiempo de garantía")
        $tc = $this->record->technicalComposition;
        $model = $this->record->productModel;
        if ($tc && $model?->warranty_years && empty($this->productTcFields['support_material'] ?? null)) {
            $tc->updateQuietly(['support_material' => "{$model->warranty_years} años"]);
        }

        // ── Auto-crear LabelBatch ─────────────────────────────────────────────
        $quantity = $this->data['default_label_quantity'] ?? 100;
        $batch = LabelBatch::create([
            'product_id' => $this->record->id,
            'quantity'   => $quantity,
        ]);

        // Generar seriales inmediatamente
        app(SerialGeneratorService::class)->generateLabelsForBatch($batch);
    }
}
