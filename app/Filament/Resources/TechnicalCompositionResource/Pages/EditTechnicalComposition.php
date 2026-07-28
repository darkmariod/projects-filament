<?php

namespace App\Filament\Resources\TechnicalCompositionResource\Pages;

use App\Filament\Resources\TechnicalCompositionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTechnicalComposition extends EditRecord
{
    protected static string $resource = TechnicalCompositionResource::class;

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
