<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\TechnicalComposition;

/**
 * CatalogImport — importa un catálogo completo de productos desde arrays de filas.
 *
 * Procesa 4 entidades en orden de dependencia: categorías → modelos → productos → composiciones.
 * Usa find-or-create (upsert por identificador de negocio) para ser idempotente.
 */
class CatalogImport
{
    private array $categories;
    private array $models;
    private array $products;
    private array $compositions;

    public function __construct(
        array $categories = [],
        array $models = [],
        array $products = [],
        array $composiciones = [],
    ) {
        $this->categories   = $categories;
        $this->models       = $models;
        $this->products     = $products;
        $this->compositions = $composiciones;
    }

    /**
     * Procesa las 4 hojas en orden de dependencia.
     *
     * @return array{created: int, skipped: int, errors: int}
     */
    public function import(): array
    {
        $created = 0;
        $skipped = 0;
        $errors  = 0;

        // ── 1. Categorías ─────────────────────────────────────────────
        foreach ($this->categories as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $errors++;
                continue;
            }

            $existing = Category::where('name', $name)->first();
            if ($existing) {
                $existing->update([
                    'code'        => $row['code'] ?? $existing->code,
                    'description' => $row['description'] ?? $existing->description,
                ]);
                $skipped++;
            } else {
                Category::create([
                    'name'        => $name,
                    'code'        => $row['code'] ?? uniqid('CAT-'),
                    'description' => $row['description'] ?? '',
                ]);
                $created++;
            }
        }

        // ── 2. Modelos ────────────────────────────────────────────────
        foreach ($this->models as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $errors++;
                continue;
            }

            $categoryId = null;
            $categoryName = trim((string) ($row['categoria'] ?? ''));
            if ($categoryName !== '') {
                $category = Category::firstOrCreate(
                    ['name' => $categoryName],
                    ['code' => uniqid('CAT-'), 'description' => '']
                );
                $categoryId = $category->id;
            }

            $existing = ProductModel::where('name', $name)->first();
            if ($existing) {
                $existing->update([
                    'category_id'    => $categoryId ?? $existing->category_id,
                    'code'           => $row['code'] ?? $existing->code,
                    'type'           => $row['type'] ?? $existing->type,
                    'class'          => $row['class'] ?? $existing->class,
                    'warranty_years' => $row['warranty_years'] ?? $existing->warranty_years,
                ]);
                $skipped++;
            } else {
                ProductModel::create([
                    'category_id'    => $categoryId,
                    'name'           => $name,
                    'code'           => $row['code'] ?? uniqid('MOD-'),
                    'type'           => $row['type'] ?? null,
                    'class'          => $row['class'] ?? null,
                    'warranty_years' => $row['warranty_years'] ?? 1,
                ]);
                $created++;
            }
        }

        // ── 3. Productos ──────────────────────────────────────────────
        foreach ($this->products as $row) {
            $productCode = trim((string) ($row['product_code'] ?? ''));
            if ($productCode === '') {
                $errors++;
                continue;
            }

            $modelId = null;
            $modelName = trim((string) ($row['modelo'] ?? ''));
            if ($modelName !== '') {
                $model = ProductModel::firstOrCreate(
                    ['name' => $modelName],
                    [
                        'category_id'    => null,
                        'code'           => uniqid('MOD-'),
                        'type'           => null,
                        'class'          => null,
                        'warranty_years' => 1,
                    ]
                );
                $modelId = $model->id;
            }

            $existing = Product::where('product_code', $productCode)->first();
            if ($existing) {
                $existing->update([
                    'product_model_id'  => $modelId ?? $existing->product_model_id,
                    'name'              => $row['name'] ?? $existing->name,
                    'barcode'           => $row['barcode'] ?? $existing->barcode,
                    'measurements_text' => $row['measurements_text'] ?? $existing->measurements_text,
                    'class'             => $row['class'] ?? $existing->class,
                    'plazas'            => $row['plazas'] ?? $existing->plazas,
                ]);
                $skipped++;
            } else {
                Product::create([
                    'product_model_id'  => $modelId,
                    'name'              => $row['name'] ?? $productCode,
                    'product_code'      => $productCode,
                    'barcode'           => $row['barcode'] ?? null,
                    'measurements_text' => $row['measurements_text'] ?? null,
                    'class'             => $row['class'] ?? null,
                    'plazas'            => $row['plazas'] ?? null,
                ]);
                $created++;
            }
        }

        // ── 4. Composiciones ──────────────────────────────────────────
        foreach ($this->compositions as $row) {
            $productCode = trim((string) ($row['codigo_producto'] ?? ''));
            if ($productCode === '') {
                $errors++;
                continue;
            }

            $product = Product::where('product_code', $productCode)->first();
            if (!$product) {
                $errors++;
                continue;
            }

            $existing = TechnicalComposition::where('product_id', $product->id)->first();
            $data = [
                'product_id'                 => $product->id,
                'commercial_name'            => $row['commercial_name'] ?? '',
                'cover_material'             => $row['cover_material'] ?? null,
                'springs'                    => $row['springs'] ?? null,
                'foam_description'           => $row['foam_description'] ?? null,
                'support_material'           => $row['support_material'] ?? null,
                'conservation_instructions'  => $row['conservation_instructions'] ?? null,
                'legal_text'                 => $row['legal_text'] ?? null,
                'inen_standard'              => $row['inen_standard'] ?? null,
                'manufacturer'               => $row['manufacturer'] ?? null,
                'manufacturer_ruc'           => $row['manufacturer_ruc'] ?? null,
                'manufacturer_address'       => $row['manufacturer_address'] ?? null,
                'website'                    => $row['website'] ?? null,
            ];

            if ($existing) {
                $existing->update($data);
                $skipped++;
            } else {
                TechnicalComposition::create($data);
                $created++;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
        ];
    }
}
