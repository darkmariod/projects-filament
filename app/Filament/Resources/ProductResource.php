<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\ProductModel;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Productos';
    protected static ?string $modelLabel = 'Producto';
    protected static ?string $pluralModelLabel = 'Productos';
    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', Product::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Datos del producto')
                    ->schema([
                        Forms\Components\Select::make('product_model_id')
                            ->label('Modelo')
                            ->options(ProductModel::where('active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del producto')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('product_code')
                            ->label('Código de producto')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),

                        Forms\Components\TextInput::make('barcode')
                            ->label('Código de barras')
                            ->nullable()
                            ->maxLength(50),

                        Forms\Components\FileUpload::make('image')
                            ->label('Imagen del producto')
                            ->image()
                            ->disk('public')
                            ->directory('products')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->default(true),
                    ])->columns(2),

                Section::make('Plaza')
                    ->schema([
                        Forms\Components\TextInput::make('width_cm')
                            ->label('Ancho (cm)')
                            ->numeric()
                            ->nullable(),

                        Forms\Components\TextInput::make('length_cm')
                            ->label('Largo (cm)')
                            ->numeric()
                            ->nullable(),

                        Forms\Components\TextInput::make('height_cm')
                            ->label('Alto (cm)')
                            ->numeric()
                            ->nullable(),

                        Forms\Components\TextInput::make('measurements_text')
                            ->label('Medidas en texto')
                            ->nullable()
                            ->maxLength(100),
                    ])->columns(2),

                Section::make('Descripción')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product_code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('productModel.name')
                    ->label('Modelo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('measurements_text')
                    ->label('Medidas')
                    ->searchable(),

                Tables\Columns\TextColumn::make('barcode')
                    ->label('Código de barras')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_model_id')
                    ->label('Modelo')
                    ->relationship('productModel', 'name'),
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn(Product $record): bool => Auth::user()?->can('update', $record) ?? false),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn(Product $record): bool => Auth::user()?->can('delete', $record) ?? false),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}