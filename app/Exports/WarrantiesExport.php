<?php

namespace App\Exports;

use App\Models\Warranty;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WarrantiesExport implements
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
        $query = Warranty::with([
            'customer',
            'label.product.productModel',
            'label.labelBatch',
        ])->orderBy('created_at', 'desc');

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }

        if (!empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        if (!empty($this->filters['product_id'])) {
            $query->whereHas('label', fn($q) => $q->where('product_id', $this->filters['product_id']));
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
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
    }

    public function map($warranty): array
    {
        return [
            $warranty->customer->first_name . ' ' . $warranty->customer->last_name,
            strtoupper($warranty->customer->document_type ?? ''),
            $warranty->customer->document_number ?? '',
            $warranty->customer->phone ?? '',
            $warranty->customer->email ?? '',
            $warranty->customer->province ?? '',
            $warranty->customer->city ?? '',
            $warranty->label->product->name ?? '',
            $warranty->label->product->productModel->name ?? '',
            $warranty->label->product->measurements_text ?? '',
            $warranty->label->labelBatch->customer_batch_number ?? '',
            $warranty->label->serial ?? '',
            $warranty->store_name ?? '',
            $warranty->invoice_number ?? '',
            $warranty->purchase_date?->format('d/m/Y') ?? '',
            $warranty->created_at?->format('d/m/Y') ?? '',
            $warranty->warranty_start_date?->format('d/m/Y') ?? '',
            $warranty->warranty_end_date?->format('d/m/Y') ?? '',
            match ($warranty->status) {
                'active'    => 'Activa',
                'expired'   => 'Vencida',
                'anulled'   => 'Anulada',
                default     => $warranty->status,
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
