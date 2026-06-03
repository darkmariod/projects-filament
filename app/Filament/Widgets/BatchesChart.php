<?php

namespace App\Filament\Widgets;

use App\Models\LabelBatch;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class BatchesChart extends ChartWidget
{
    protected ?string $heading = 'Lotes (30 días)';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $results = LabelBatch::where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $labels = [];
        $data = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = $date;
            $data[] = (int) ($results[$date] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Lotes',
                    'data' => $data,
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
