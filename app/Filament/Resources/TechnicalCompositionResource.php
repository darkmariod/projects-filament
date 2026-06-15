<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TechnicalCompositionResource\Pages;
use App\Models\TechnicalComposition;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TechnicalCompositionResource extends Resource
{
    protected static ?string $model = TechnicalComposition::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationLabel = 'Composiciones';
    protected static ?string $modelLabel = 'Composición Técnica';
    protected static ?string $pluralModelLabel = 'Composiciones Técnicas';
    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', TechnicalComposition::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Producto')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Producto')
                            ->options(Product::where('active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->unique(ignoreRecord: true)
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if (!$state) return;

                                // 1. Pull fields from the selected product
                                $product = \App\Models\Product::find($state);
                                if ($product) {
                                    $set('commercial_name', $product->commercial_name);
                                    $set('product_family', $product->product_family);
                                    $set('conservation_instructions', $product->conservation_instructions);
                                    $set('springs', $product->springs);
                                    $set('foam_description', $product->foam_description);
                                }

                                // 2. Fill remaining composition fields from active template
                                $template = TechnicalComposition::where('active', true)->first();
                                if ($template) {
                                    $set('cover_material', $template->cover_material);
                                    // Only set from template if product didn't provide a value
                                    if (!$product?->springs) $set('springs', $template->springs);
                                    if (!$product?->foam_description) $set('foam_description', $template->foam_description);
                                    if (!$product?->conservation_instructions) $set('conservation_instructions', $template->conservation_instructions);
                                    $set('support_material', $template->support_material);
                                    $set('general_composition', $template->general_composition);
                                    $set('legal_text', $template->legal_text);
                                    $set('inen_standard', $template->inen_standard);
                                    $set('manufacturing_country', $template->manufacturing_country);
                                    $set('manufacturer', $template->manufacturer);
                                    $set('manufacturer_ruc', $template->manufacturer_ruc);
                                    $set('manufacturer_address', $template->manufacturer_address);
                                    $set('website', $template->website);
                                }
                            }),
                    ]),

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
                            ->label('Soporte')
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.product_code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('commercial_name')
                    ->label('Nombre comercial')
                    ->searchable(),

                Tables\Columns\TextColumn::make('product_family')
                    ->label('Familia')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('manufacturer')
                    ->label('Fabricante')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn(TechnicalComposition $record): bool => Auth::user()?->can('update', $record) ?? false),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn(TechnicalComposition $record): bool => Auth::user()?->can('delete', $record) ?? false),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTechnicalCompositions::route('/'),
            'create' => Pages\CreateTechnicalComposition::route('/create'),
            'edit'   => Pages\EditTechnicalComposition::route('/{record}/edit'),
        ];
    }
}