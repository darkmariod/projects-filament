<?php

namespace App\Filament\Resources\TechnicalCompositionResource\Pages;

use App\Filament\Resources\TechnicalCompositionResource;
use App\Models\TechnicalComposition;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTechnicalCompositions extends ListRecords
{
    protected static string $resource = TechnicalCompositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear')
                ->visible(fn(): bool => auth()->user()?->can('create', TechnicalComposition::class) ?? false),
        ];
    }
}