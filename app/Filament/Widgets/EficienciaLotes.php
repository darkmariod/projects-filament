<?php

namespace App\Filament\Widgets;

use App\Models\Label;
use App\Models\LabelBatch;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EficienciaLotes extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalLotes = LabelBatch::count();
        $totalLabels = Label::count();
        $labelsConGarantia = Label::whereHas('warranty')->count();
        $eficiencia = $totalLabels > 0 ? round(($labelsConGarantia / $totalLabels) * 100, 1) : 0;

        return [
            Stat::make('Total lotes', $totalLotes)
                ->description('Lotes generados'),

            Stat::make('Etiquetas con garantía', $labelsConGarantia)
                ->description("De {$totalLabels} etiquetas totales"),

            Stat::make('Eficiencia', "{$eficiencia}%")
                ->description('Porcentaje con garantía registrada')
                ->color($eficiencia > 50 ? 'success' : ($eficiencia > 20 ? 'warning' : 'danger')),
        ];
    }
}
