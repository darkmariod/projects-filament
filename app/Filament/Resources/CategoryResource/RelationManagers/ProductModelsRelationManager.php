<?php

namespace App\Filament\Resources\CategoryResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class ProductModelsRelationManager extends RelationManager
{
    protected static string $relationship = 'productModels';

    protected static ?string $recordTitleAttribute = 'name';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre del modelo')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set) {
                        if (!$state) return;
                        $code = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $state);
                        $code = strtoupper($code);
                        $code = preg_replace('/[^A-Z0-9]+/', '_', $code);
                        $code = trim($code, '_');
                        $set('code', $code);
                    }),

                Forms\Components\TextInput::make('code')
                    ->label('Código')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),

                Forms\Components\TextInput::make('type')
                    ->label('Tipo')
                    ->nullable()
                    ->maxLength(100),

                Forms\Components\TextInput::make('class')
                    ->label('Clase')
                    ->nullable()
                    ->maxLength(100),

                Forms\Components\TextInput::make('warranty_years')
                    ->label('Años de garantía')
                    ->numeric()
                    ->default(1)
                    ->required(),

                Forms\Components\Toggle::make('active')
                    ->label('Activo')
                    ->default(true),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Modelo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('class')
                    ->label('Clase')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('warranty_years')
                    ->label('Garantía')
                    ->suffix(' años')
                    ->badge()
                    ->color('success'),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Crear Modelo'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar'),
                DeleteAction::make()
                    ->label('Eliminar'),
            ])
            ->bulkActions([]);
    }
}
