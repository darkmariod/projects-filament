<?php

namespace App\Filament\Resources\ProductModelResource\Pages;

use App\Filament\Resources\ProductModelResource;
use App\Models\ProductModel;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductModels extends ListRecords
{
    protected static string $resource = ProductModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear')
                ->visible(fn(): bool => auth()->user()?->can('create', ProductModel::class) ?? false),
        ];
    }
}