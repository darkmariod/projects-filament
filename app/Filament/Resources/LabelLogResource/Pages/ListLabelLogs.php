<?php

namespace App\Filament\Resources\LabelLogResource\Pages;

use App\Filament\Resources\LabelLogResource;
use Filament\Resources\Pages\ListRecords;

class ListLabelLogs extends ListRecords
{
    protected static string $resource = LabelLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
