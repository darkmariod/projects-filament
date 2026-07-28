<?php

namespace App\Filament\Resources\TechnicalCompositionResource\Pages;

use App\Filament\Resources\TechnicalCompositionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTechnicalComposition extends CreateRecord
{
    protected static string $resource = TechnicalCompositionResource::class;

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()->label('Crear');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
