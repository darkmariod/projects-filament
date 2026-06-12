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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['width_dots'] = (int) round(
            ($data['label_width_mm'] ?? 0) * ($data['dpi'] ?? 203) / 25.4
        );
        $data['height_dots'] = (int) round(
            ($data['label_height_mm'] ?? 0) * ($data['dpi'] ?? 203) / 25.4
        );
        return $data;
    }
}
