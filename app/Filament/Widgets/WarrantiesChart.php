<?php

namespace App\Filament\Widgets;

use App\Models\Warranty;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class WarrantiesChart extends ChartWidget
{
    protected ?string $heading = 'Garantías (30 días)';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $results = Warranty::where('created_at', '>=', now()->subDays(30))
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
                    'label' => 'Garantías',
                    'data' => $data,
                    'borderColor' => '#22c55e',
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
