<?php

declare(strict_types=1);

namespace Tests\Feature\Imports;

use App\Imports\CatalogImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\TechnicalComposition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function import_creates_all_entity_types_from_rows(): void
    {
        $categories = [
            ['name' => 'Matrimonial', 'code' => 'CAT-001', 'description' => 'Colchones matrimoniales'],
            ['name' => 'Individual', 'code' => 'CAT-002', 'description' => ''],
        ];

        $models = [
            ['categoria' => 'Matrimonial', 'name' => 'MATREX', 'code' => 'MOD-001', 'type' => 'Tipo IV', 'class' => 'A', 'warranty_years' => 5],
            ['categoria' => 'Individual', 'name' => 'IND-PLUS', 'code' => 'MOD-002', 'type' => 'Tipo II', 'class' => 'B', 'warranty_years' => 3],
        ];

        $products = [
            ['modelo' => 'MATREX', 'name' => 'Matrex Original', 'product_code' => 'MX-001', 'barcode' => '7890000001', 'measurements_text' => '150x190x30', 'class' => 'Clase A', 'plazas' => '1 1/2 PLZ'],
            ['modelo' => 'IND-PLUS', 'name' => 'Ind Plus', 'product_code' => 'IP-001', 'barcode' => '7890000002', 'measurements_text' => '100x190x25', 'class' => 'Clase B', 'plazas' => '1 PLZ'],
        ];

        $compositions = [
            ['codigo_producto' => 'MX-001', 'cover_material' => 'Tela', 'springs' => 'Bonnell', 'foam_description' => 'Espuma HD', 'manufacturer' => 'Paraíso', 'manufacturer_ruc' => '1790098230001'],
            ['codigo_producto' => 'IP-001', 'cover_material' => 'Seda', 'springs' => 'Pocket', 'foam_description' => 'Espuma Visco', 'manufacturer' => 'Paraíso', 'manufacturer_ruc' => '1790098230001'],
        ];

        $import = new CatalogImport($categories, $models, $products, $compositions);
        $import->import();

        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseCount('product_models', 2);
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('technical_compositions', 2);

        $this->assertDatabaseHas('categories', ['name' => 'Matrimonial', 'code' => 'CAT-001']);
        $this->assertDatabaseHas('product_models', ['name' => 'MATREX', 'code' => 'MOD-001']);
        $this->assertDatabaseHas('products', ['product_code' => 'MX-001', 'name' => 'Matrex Original']);
        $this->assertDatabaseHas('technical_compositions', ['product_id' => Product::where('product_code', 'MX-001')->first()->id]);
    }

    /** @test */
    public function import_duplicate_sku_is_skipped_and_returns_skipped_count(): void
    {
        $categories = [
            ['name' => 'Matrimonial', 'code' => 'CAT-001', 'description' => ''],
        ];
        $models = [
            ['categoria' => 'Matrimonial', 'name' => 'MATREX', 'code' => 'MOD-001', 'type' => 'Tipo IV', 'class' => 'A', 'warranty_years' => 5],
        ];
        $products = [
            ['modelo' => 'MATREX', 'name' => 'Matrex Original', 'product_code' => 'MX-001', 'barcode' => '7890000001', 'measurements_text' => '150x190x30', 'class' => 'Clase A', 'plazas' => '1 1/2 PLZ'],
        ];
        $compositions = [];

        // First import — category + model + product = 3 created
        $import = new CatalogImport($categories, $models, $products, $compositions);
        $result1 = $import->import();
        $this->assertSame(3, $result1['created']);
        $this->assertSame(0, $result1['skipped']);

        // Second import — same data → all skipped
        $import2 = new CatalogImport($categories, $models, $products, $compositions);
        $result2 = $import2->import();
        $this->assertSame(0, $result2['created']);
        $this->assertSame(3, $result2['skipped']);

        // Still only one product, one category, one model
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('product_models', 1);
        $this->assertDatabaseCount('products', 1);
    }

    /** @test */
    public function import_updates_existing_records_instead_of_duplicating(): void
    {
        $categories = [['name' => 'Matrimonial', 'code' => 'CAT-001', 'description' => '']];
        $models = [['categoria' => 'Matrimonial', 'name' => 'MATREX', 'code' => 'MOD-001', 'type' => 'Tipo IV', 'class' => 'A', 'warranty_years' => 5]];
        $products = [['modelo' => 'MATREX', 'name' => 'Matrex Original', 'product_code' => 'MX-001', 'barcode' => '7890000001', 'measurements_text' => '150x190x30', 'class' => 'Clase A', 'plazas' => '1 1/2 PLZ']];
        $compositions = [['codigo_producto' => 'MX-001', 'cover_material' => 'Tela', 'springs' => 'Bonnell', 'foam_description' => 'Espuma HD', 'manufacturer' => 'Paraíso', 'manufacturer_ruc' => '1790098230001']];

        (new CatalogImport($categories, $models, $products, $compositions))->import();

        // Update name on second import
        $products[0]['name'] = 'Matrex Plus';
        $compositions[0]['cover_material'] = 'Seda';

        (new CatalogImport($categories, $models, $products, $compositions))->import();

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', ['product_code' => 'MX-001', 'name' => 'Matrex Plus']);
        $this->assertDatabaseCount('technical_compositions', 1);
        $this->assertDatabaseHas('technical_compositions', ['cover_material' => 'Seda']);
    }

    /** @test */
    public function template_export_returns_valid_xlsx_with_correct_sheets(): void
    {
        $export = new \App\Exports\CatalogTemplateExport();

        $sheets = $export->sheets();

        $this->assertArrayHasKey('categorias', $sheets);
        $this->assertArrayHasKey('modelos', $sheets);
        $this->assertArrayHasKey('productos', $sheets);
        $this->assertArrayHasKey('composiciones', $sheets);
    }
}
