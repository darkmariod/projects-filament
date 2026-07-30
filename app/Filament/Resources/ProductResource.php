<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\Category;
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
    protected static string|\UnitEnum|null $navigationGroup = 'Productos';
    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', Product::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identificación')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('productModel.category_id')
                            ->label('Categoría/Empresa')
                            ->options(Category::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre de la categoría/empresa')
                                    ->required(),
                                Forms\Components\TextInput::make('code')
                                    ->label('Código')
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('product_model_id')
                            ->label('Modelo de colchón')
                            ->options(ProductModel::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\Select::make('category_id')
                                    ->label('Categoría')
                                    ->options(Category::pluck('name', 'id'))
                                    ->required(),
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre del modelo')
                                    ->required(),
                                Forms\Components\TextInput::make('code')
                                    ->label('Código')
                                    ->required(),
                                Forms\Components\TextInput::make('type')
                                    ->label('Tipo')
                                    ->nullable(),
                                Forms\Components\TextInput::make('class')
                                    ->label('Clase')
                                    ->nullable(),
                                Forms\Components\TextInput::make('warranty_years')
                                    ->label('Años de garantía')
                                    ->numeric()
                                    ->default(1),
                            ]),

                        Forms\Components\TextInput::make('product_code')
                            ->label('Código de producto')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del producto')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('commercial_name')
                            ->label('Nombre comercial')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('product_family')
                            ->label('Familia de producto')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('barcode')
                            ->label('Código de barras')
                            ->nullable()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('default_label_quantity')
                            ->label('Cantidad de etiquetas (default)')
                            ->helperText('Se usará al auto-crear el lote de etiquetas. Dejá vacío para usar 100.')
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->maxLength(6),

                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->default(true),
                    ]),

                Section::make('Imagen de la etiqueta')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Logo del producto')
                            ->image()
                            ->disk('public')
                            ->directory('products')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),
                    ]),

                Section::make('Medidas (Plaza)')
                    ->columns(4)
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
                    ]),

                Section::make('Materiales y conservación')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('springs')
                            ->label('Resortes')
                            ->nullable()
                            ->maxLength(255)
                            ->default(fn() => static::getDefaultField('springs')),

                        Forms\Components\TextInput::make('foam_description')
                            ->label('Espuma')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('conservation_instructions')
                            ->label('Instrucciones de conservación')
                            ->nullable()
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Datos del fabricante')
                    ->columns(3)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('manufacturer')
                            ->label('Fabricante')
                            ->nullable()
                            ->maxLength(255)
                            ->default(fn() => static::getDefaultManufacturer('manufacturer')),

                        Forms\Components\TextInput::make('manufacturer_ruc')
                            ->label('RUC')
                            ->nullable()
                            ->maxLength(50)
                            ->default(fn() => static::getDefaultManufacturer('manufacturer_ruc')),

                        Forms\Components\TextInput::make('manufacturer_address')
                            ->label('Dirección')
                            ->nullable()
                            ->maxLength(255)
                            ->default(fn() => static::getDefaultManufacturer('manufacturer_address')),

                        Forms\Components\TextInput::make('manufacturing_country')
                            ->label('País')
                            ->nullable()
                            ->maxLength(100)
                            ->default(fn() => static::getDefaultManufacturer('manufacturing_country')),

                        Forms\Components\TextInput::make('website')
                            ->label('Sitio web')
                            ->nullable()
                            ->columnSpan(2)
                            ->maxLength(255)
                            ->default(fn() => static::getDefaultManufacturer('website')),
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
                    ->visible(fn(Product $record): bool => Auth::user()?->can('delete', $record) ?? false)
                    ->action(function (Product $record, DeleteAction $action): void {
                        try {
                            $record->delete();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('No se pudo eliminar el producto')
                                ->body($e->getMessage())
                                ->danger()
                                ->seconds(8)
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getDefaultManufacturer(string $field): ?string
    {
        static $template = null;
        if ($template === null) {
            $template = \App\Models\TechnicalComposition::where('active', true)->first();
        }
        return $template?->{$field};
    }

    public static function getDefaultField(string $field): ?string
    {
        static $template = null;
        if ($template === null) {
            $template = \App\Models\TechnicalComposition::where('active', true)->first();
        }
        return $template?->{$field};
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