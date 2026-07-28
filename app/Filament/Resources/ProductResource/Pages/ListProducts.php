<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Exports\CatalogTemplateExport;
use App\Filament\Resources\ProductResource;
use App\Imports\CatalogImport;
use App\Models\Product;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear')
                ->visible(fn (): bool => auth()->user()?->can('create', Product::class) ?? false),

            Actions\Action::make('importar')
                ->label('Importar Catálogo')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    Excel::import(new CatalogImport, $data['file']);

                    Notification::make()
                        ->success('Catálogo importado correctamente')
                        ->send();
                }),

            Actions\Action::make('template')
                ->label('Descargar Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => Excel::download(new CatalogTemplateExport, 'template-catalogo.xlsx')),
        ];
    }
}   