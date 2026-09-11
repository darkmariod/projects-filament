<?php

namespace App\Filament\Widgets;

use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\Warranty;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            // Las anuladas no se fabricaron: contarlas inflaría la producción.
            Stat::make('Etiquetas Producidas', Label::where('status', '!=', 'anulled')->count())
                ->description(Label::where('status', 'anulled')->count() . ' anuladas')
                ->descriptionIcon('heroicon-m-tag')
                ->color('info'),

            Stat::make('Garantías Activas', Warranty::where('status', 'active')->count())
                ->description('Garantías vigentes')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success'),

            Stat::make('Lotes Este Mes', LabelBatch::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count())
                ->description('Lotes generados en el mes')
                ->descriptionIcon('heroicon-m-cube')
                ->color('warning'),

            Stat::make('Productos', Product::where('active', true)
                ->where('product_code', '!=', config('dashboard.demo_product_code'))
                ->count())
                ->description('Productos activos')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('primary'),
        ];
    }
}
