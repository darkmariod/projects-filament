<?php

namespace App\Filament\Resources\WarrantyResource\Pages;

use App\Filament\Resources\WarrantyResource;
use App\Models\Warranty;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateWarranty extends CreateRecord
{
    protected static string $resource = WarrantyResource::class;

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()->label('Crear');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        $record = $this->getRecord();

        if (! $record instanceof Warranty) {
            return null;
        }

        return Notification::make()
            ->title('Garantía registrada')
            ->body("Garantía registrada correctamente para el serial {$record->label->serial}")
            ->success()
            ->seconds(5);
    }
}
