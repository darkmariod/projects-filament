<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * CatalogTemplateExport — genera un Excel plantilla con 4 hojas vacías
 * (solo encabezados) para que el usuario llene y luego importe con CatalogImport.
 */
class CatalogTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'categorias'    => new CategoriaTemplateSheet(),
            'modelos'       => new ModeloTemplateSheet(),
            'productos'     => new ProductoTemplateSheet(),
            'composiciones' => new ComposicionTemplateSheet(),
        ];
    }
}

/**
 * Hoja de plantilla para categorías.
 */
class CategoriaTemplateSheet implements \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\FromCollection
{
    public function headings(): array
    {
        return ['name', 'code', 'description'];
    }

    public function collection(): \Illuminate\Support\Collection
    {
        return collect([
            ['name' => 'Ej: Matrimonial', 'code' => 'Ej: CAT-001', 'description' => 'Descripción opcional'],
        ]);
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B0000']]]];
    }
}

/**
 * Hoja de plantilla para modelos.
 */
class ModeloTemplateSheet implements \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\FromCollection
{
    public function headings(): array
    {
        return ['categoria', 'name', 'code', 'type', 'class', 'warranty_years'];
    }

    public function collection(): \Illuminate\Support\Collection
    {
        return collect([
            ['categoria' => 'Ej: Matrimonial', 'name' => 'Ej: MATREX', 'code' => 'Ej: MOD-001', 'type' => 'Ej: Tipo IV', 'class' => 'A', 'warranty_years' => 5],
        ]);
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B0000']]]];
    }
}

/**
 * Hoja de plantilla para productos.
 */
class ProductoTemplateSheet implements \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\FromCollection
{
    public function headings(): array
    {
        return ['modelo', 'name', 'product_code', 'barcode', 'measurements_text', 'class', 'plazas'];
    }

    public function collection(): \Illuminate\Support\Collection
    {
        return collect([
            ['modelo' => 'Ej: MATREX', 'name' => 'Ej: Matrex Original', 'product_code' => 'Ej: MX-001', 'barcode' => 'Ej: 7890000001', 'measurements_text' => 'Ej: 150x190x30', 'class' => 'Ej: Clase A', 'plazas' => 'Ej: 1 1/2 PLZ'],
        ]);
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B0000']]]];
    }
}

/**
 * Hoja de plantilla para composiciones técnicas.
 */
class ComposicionTemplateSheet implements \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\FromCollection
{
    public function headings(): array
    {
        return ['codigo_producto', 'cover_material', 'springs', 'foam_description', 'manufacturer', 'manufacturer_ruc', 'manufacturer_address'];
    }

    public function collection(): \Illuminate\Support\Collection
    {
        return collect([
            ['codigo_producto' => 'Ej: MX-001', 'cover_material' => 'Ej: Tela', 'springs' => 'Ej: Bonnell', 'foam_description' => 'Ej: Espuma HD', 'manufacturer' => 'Ej: Paraíso', 'manufacturer_ruc' => 'Ej: 1790098230001', 'manufacturer_address' => 'Ej: Av. Panamericana'],
        ]);
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B0000']]]];
    }
}
