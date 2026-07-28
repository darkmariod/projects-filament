<?php

namespace App\Exports;

use App\Models\LabelBatch;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LabelBatchesExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    ShouldAutoSize
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection(): Collection
    {
        $query = LabelBatch::with([
            'product.productModel',
            'generatedBy',
        ])->orderBy('created_at', 'desc');

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['product_id'])) {
            $query->where('product_id', $this->filters['product_id']);
        }

        if (!empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }

        if (!empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Código interno',
            'Producto',
            'Cantidad',
            'Número lote cliente',
            'Operador',
            'Generado por',
            'Fecha generación',
            'Observaciones',
            'Estado',
        ];
    }

    public function map($batch): array
    {
        return [
            $batch->internal_batch_code ?? '',
            $batch->product?->name ?? '',
            $batch->quantity ?? 0,
            $batch->customer_batch_number ?? '',
            $batch->operator ?? '',
            $batch->generatedBy?->name ?? '',
            $batch->generated_at?->format('d/m/Y H:i') ?? '',
            $batch->observations ?? '',
            match ($batch->status) {
                'generated' => 'Generado',
                'printed'   => 'Impreso',
                'active'    => 'Activo',
                'anulled'   => 'Anulado',
                default     => $batch->status,
            },
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType'   => 'solid',
                    'startColor' => ['rgb' => '8B0000'],
                ],
            ],
        ];
    }
}
