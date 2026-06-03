<?php

namespace App\Filament\Resources\LabelBatchResource\Pages;

use App\Filament\Resources\LabelBatchResource;
use App\Models\LabelBatch;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLabelBatches extends ListRecords
{
    protected static string $resource = LabelBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear')
                ->visible(fn(): bool => auth()->user()?->can('create', LabelBatch::class) ?? false),
        ];
    }
}
