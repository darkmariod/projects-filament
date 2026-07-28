<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\TechnicalComposition;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TechnicalCompositionRelationManager extends RelationManager
{
    protected static string $relationship = 'technicalComposition';

    protected static ?string $recordTitleAttribute = 'commercial_name';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identificación')
                    ->schema([
                        Forms\Components\TextInput::make('commercial_name')
                            ->label('Nombre comercial')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('product_family')
                            ->label('Familia de producto')
                            ->nullable()
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Materiales y composición')
                    ->schema([
                        Forms\Components\TextInput::make('cover_material')
                            ->label('Forro')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('springs')
                            ->label('Resortes')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('foam_description')
                            ->label('Espuma')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('support_material')
                            ->label('Tiempo de garantía')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('general_composition')
                            ->label('Composición general')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Cuidado y conservación')
                    ->schema([
                        Forms\Components\Textarea::make('conservation_instructions')
                            ->label('Instrucciones de conservación')
                            ->nullable()
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('legal_text')
                            ->label('Texto legal / ambiental')
                            ->nullable()
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Datos del fabricante')
                    ->schema([
                        Forms\Components\TextInput::make('inen_standard')
                            ->label('Norma INEN')
                            ->nullable()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('manufacturing_country')
                            ->label('País de fabricación')
                            ->nullable()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('manufacturer')
                            ->label('Fabricante')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('manufacturer_ruc')
                            ->label('RUC del fabricante')
                            ->nullable()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('manufacturer_address')
                            ->label('Dirección del fabricante')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('website')
                            ->label('Sitio web')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('commercial_name')
            ->columns([
                TextColumn::make('commercial_name')
                    ->label('Nombre comercial')
                    ->searchable(),

                TextColumn::make('product_family')
                    ->label('Familia'),

                TextColumn::make('cover_material')
                    ->label('Forro')
                    ->limit(30),

                TextColumn::make('foam_description')
                    ->label('Espuma')
                    ->limit(30),

                TextColumn::make('inen_standard')
                    ->label('Norma INEN'),

                \Filament\Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->headerActions([
                \Filament\Tables\Actions\CreateAction::make()
                    ->label('Crear Composición Técnica')
                    ->visible(fn(): bool => $this->getOwnerRecord()->technicalComposition === null),
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
