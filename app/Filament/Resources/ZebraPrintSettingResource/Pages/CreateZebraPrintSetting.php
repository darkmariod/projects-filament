<?php

namespace App\Filament\Resources\ZebraPrintSettingResource\Pages;

use App\Filament\Resources\ZebraPrintSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateZebraPrintSetting extends CreateRecord
{
    protected static string $resource = ZebraPrintSettingResource::class;

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()->label('Crear');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
