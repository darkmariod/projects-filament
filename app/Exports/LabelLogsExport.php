<?php

namespace App\Exports;

use App\Models\LabelLog;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LabelLogsExport implements
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
        $query = LabelLog::with([
            'user',
            'label',
            'labelBatch',
        ])->orderBy('created_at', 'desc');

        if (!empty($this->filters['action'])) {
            $query->where('action', $this->filters['action']);
        }

        if (!empty($this->filters['user_id'])) {
            $query->where('user_id', $this->filters['user_id']);
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
            'Fecha',
            'Usuario',
            'Acción',
            'Descripción',
            'Serial',
            'Lote',
            'IP',
        ];
    }

    public function map($log): array
    {
        return [
            $log->created_at?->format('d/m/Y H:i:s') ?? '',
            $log->user?->name ?? '',
            $log->action ?? '',
            $log->description ?? '',
            $log->label?->serial
                ?? ($log->labelBatch?->serial_from
                    ? "{$log->labelBatch->serial_from} → {$log->labelBatch->serial_to}"
                    : ''),
            $log->labelBatch?->internal_batch_code ?? '',
            $log->ip ?? '',
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
