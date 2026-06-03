<?php

namespace App\Filament\Resources\ZebraPrintSettingResource\Pages;

use App\Filament\Resources\ZebraPrintSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditZebraPrintSetting extends EditRecord
{
    protected static string $resource = ZebraPrintSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Eliminar')
                ->visible(fn(): bool => auth()->user()?->can('delete', $this->getRecord()) ?? false),
        ];
    }

    protected function getSaveFormAction(): \Filament\Actions\Action
    {
        return parent::getSaveFormAction()->label('Guardar');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
