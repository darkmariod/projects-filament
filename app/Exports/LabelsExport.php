<?php

namespace App\Exports;

use App\Models\Label;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LabelsExport implements
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
        $query = Label::with([
            'labelBatch',
            'product.productModel',
            'warranty.customer',
        ])->orderBy('created_at', 'desc');

        if (!empty($this->filters['label_batch_id'])) {
            $query->where('label_batch_id', $this->filters['label_batch_id']);
        }

        if (!empty($this->filters['product_id'])) {
            $query->where('product_id', $this->filters['product_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
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
            'Fecha generación',
            'Código de lote',
            'Código único',
            'Producto',
            'Modelo',
            'Medida',
            'Estado',
            'Fecha impresión',
            'Fecha registro garantía',
            'Cliente',
        ];
    }

    public function map($label): array
    {
        return [
            $label->created_at?->format('d/m/Y H:i') ?? '',
            $label->labelBatch?->internal_batch_code ?? '',
            $label->serial ?? '',
            $label->product?->name ?? '',
            $label->product?->productModel?->name ?? '',
            $label->product?->measurements_text ?? '',
            match ($label->status) {
                'available' => 'Disponible',
                'printed'   => 'Impreso',
                'registered' => 'Registrado',
                'anulled'   => 'Anulado',
                default     => $label->status,
            },
            $label->printed_at?->format('d/m/Y H:i') ?? '',
            $label->registered_at?->format('d/m/Y H:i') ?? '',
            $label->warranty?->customer?->full_name ?? '',
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
