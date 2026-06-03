<?php

namespace App\Filament\Resources\LabelResource\Pages;

use App\Filament\Resources\LabelResource;
use App\Services\SerialGeneratorService;
use Filament\Resources\Pages\CreateRecord;

class CreateLabel extends CreateRecord
{
    protected static string $resource = LabelResource::class;

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
        // Si no se proveyó qr_url, auto-generarla desde el serial
        if (empty($data['qr_url']) && !empty($data['serial'])) {
            $data['qr_url'] = app(SerialGeneratorService::class)->buildQrUrl($data['serial']);
        }

        return $data;
    }
}
