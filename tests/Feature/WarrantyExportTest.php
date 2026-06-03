<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\WarrantiesExport;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class WarrantyExportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function export_headings_are_correct(): void
    {
        $export = new WarrantiesExport();

        $headings = $export->headings();

        $expectedHeadings = [
            'Cliente',
            'Tipo Documento',
            'Documento',
            'Teléfono',
            'Correo',
            'Provincia',
            'Ciudad',
            'Producto',
            'Modelo',
            'Medidas',
            'Lote',
            'Serial',
            'Local de compra',
            'Factura',
            'Fecha de compra',
            'Fecha de registro',
            'Inicio garantía',
            'Fin garantía',
            'Estado',
        ];

        $this->assertSame($expectedHeadings, $headings);
        $this->assertCount(19, $headings);
    }

    /** @test */
    public function empty_dataset_returns_valid_collection(): void
    {
        $export = new WarrantiesExport();

        $collection = $export->collection();

        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
    }

    /** @test */
    public function export_with_data_maps_correctly(): void
    {
        $warranty = Warranty::factory()->create();
        $warranty->load([
            'customer',
            'label.product.productModel',
            'label.labelBatch',
        ]);

        $export = new WarrantiesExport();
        $collection = $export->collection();

        $this->assertCount(1, $collection);

        $mapped = $export->map($warranty);

        $customerName = $warranty->customer->first_name . ' ' . $warranty->customer->last_name;

        $this->assertSame($customerName, $mapped[0]);
        $this->assertSame($warranty->customer->document_number, $mapped[2]);
        $this->assertSame($warranty->label->product->name, $mapped[7]);
        $this->assertSame($warranty->label->serial, $mapped[11]);
        $this->assertSame('Activa', $mapped[18]);
    }

    /** @test */
    public function export_with_multiple_records_returns_all(): void
    {
        Warranty::factory()->count(3)->create();

        $export = new WarrantiesExport();
        $collection = $export->collection();

        $this->assertCount(3, $collection);
    }

    /** @test */
    public function export_status_mapping_is_correct(): void
    {
        $export = new WarrantiesExport();

        $warranty = Warranty::factory()->create(['status' => 'active']);
        $expiredWarranty = Warranty::factory()->expired()->create();
        $anulledWarranty = Warranty::factory()->anulled()->create();

        $this->assertStringContainsString('Activa', $export->map($warranty)[18]);
        $this->assertStringContainsString('Vencida', $export->map($expiredWarranty)[18]);
        $this->assertStringContainsString('Anulada', $export->map($anulledWarranty)[18]);
    }

    /** @test */
    public function export_supports_styles_method(): void
    {
        $export = new WarrantiesExport();

        $styles = $export->styles(new Worksheet());

        $this->assertIsArray($styles);
        $this->assertArrayHasKey(1, $styles);
    }
}
