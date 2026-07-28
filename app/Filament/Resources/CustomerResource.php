<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Clientes';
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    protected static string|\UnitEnum|null $navigationGroup = 'Clientes';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', Customer::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Datos personales')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('Primer nombre')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('second_name')
                            ->label('Segundo nombre')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('last_name')
                            ->label('Primer apellido')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('second_last_name')
                            ->label('Segundo apellido')
                            ->nullable()
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Documento')
                    ->schema([
                        Forms\Components\Select::make('document_type')
                            ->label('Tipo de documento')
                            ->options([
                                'DNI'      => 'DNI',
                                'RUC'      => 'RUC',
                                'Pasaporte' => 'Pasaporte',
                                'CE'       => 'Carné de Extranjería',
                                'Otro'     => 'Otro',
                            ])
                            ->default('DNI')
                            ->required(),

                        Forms\Components\TextInput::make('document_number')
                            ->label('Número de documento')
                            ->required()
                            ->maxLength(50),
                    ])->columns(2),

                Section::make('Información adicional')
                    ->schema([
                        Forms\Components\DatePicker::make('birth_date')
                            ->label('Fecha de nacimiento')
                            ->nullable(),

                        Forms\Components\Select::make('gender')
                            ->label('Género')
                            ->options([
                                'Masculino' => 'Masculino',
                                'Femenino'  => 'Femenino',
                                'Otro'      => 'Otro',
                            ])
                            ->nullable(),
                    ])->columns(2),

                Section::make('Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->nullable()
                            ->maxLength(50),

                        Forms\Components\Textarea::make('address')
                            ->label('Dirección')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Ubicación')
                    ->schema([
                        Forms\Components\TextInput::make('province')
                            ->label('Provincia')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('city')
                            ->label('Ciudad')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('sector')
                            ->label('Sector')
                            ->nullable()
                            ->maxLength(255),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nombre completo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('document_type')
                    ->label('Tipo doc.')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('document_number')
                    ->label('N° documento')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('Ciudad')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Tipo de documento')
                    ->options([
                        'DNI'      => 'DNI',
                        'RUC'      => 'RUC',
                        'Pasaporte' => 'Pasaporte',
                        'CE'       => 'Carné de Extranjería',
                        'Otro'     => 'Otro',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn(Customer $record): bool => Auth::user()?->can('update', $record) ?? false),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn(Customer $record): bool => Auth::user()?->can('delete', $record) ?? false),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit'   => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
