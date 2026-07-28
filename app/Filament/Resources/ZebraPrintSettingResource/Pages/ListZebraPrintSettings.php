<?php

namespace App\Filament\Resources\ZebraPrintSettingResource\Pages;

use App\Filament\Resources\ZebraPrintSettingResource;
use App\Models\ZebraPrintSetting;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListZebraPrintSettings extends ListRecords
{
    protected static string $resource = ZebraPrintSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear')
                ->visible(fn(): bool => auth()->user()?->can('create', ZebraPrintSetting::class) ?? false),
        ];
    }
}
