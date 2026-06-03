<?php

namespace App\Filament\Widgets;

use App\Models\Warranty;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GarantiasPorVencer extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $vencen30dias = Warranty::where('warranty_end_date', '>=', now())
            ->where('warranty_end_date', '<=', now()->addDays(30))
            ->count();

        $vencidas = Warranty::where('warranty_end_date', '<', now())->count();

        $activas = Warranty::where('warranty_end_date', '>=', now())->count();

        return [
            Stat::make('Garantías activas', $activas)
                ->description('Con vigencia vigente')
                ->color('success'),

            Stat::make('Vencen en 30 días', $vencen30dias)
                ->description('Próximas a expirar')
                ->color('warning'),

            Stat::make('Garantías vencidas', $vencidas)
                ->description('Fuera de vigencia')
                ->color('danger'),
        ];
    }
}
