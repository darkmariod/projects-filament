<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Roles y Permisos';
    protected static ?string $modelLabel = 'Rol';
    protected static ?string $pluralModelLabel = 'Roles y Permisos';
    protected static string|\UnitEnum|null $navigationGroup = 'Administración';
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', \App\Models\Role::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $grupos = static::getPermisosAgrupados();

        $items = [
            Section::make('Datos del rol')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre del rol')
                        ->required()
                        ->unique(ignoreRecord: true)
                            ->maxLength(100),

                    TextInput::make('guard_name')
                        ->label('Guard')
                        ->default('web')
                        ->required()
                        ->maxLength(50),
                ])->columns(2),
        ];

        foreach ($grupos as $grupo => $permisos) {
            $items[] = Section::make($grupo)
                ->schema([
                    CheckboxList::make('permissions')
                        ->label('')
                        ->relationship('permissions', 'name')
                        ->options(
                            Permission::whereIn('name', $permisos)
                                ->pluck('name', 'id')
                                ->toArray()
                        )
                        ->columns(2)
                        ->gridDirection('row'),
                ])
                ->collapsible()
                ->collapsed(false);
        }

        return $schema->schema($items);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Rol')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'administrador' => 'danger',
                        'produccion'    => 'warning',
                        'ventas'        => 'success',
                        'consulta'      => 'info',
                        default         => 'gray',
                    }),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permisos asignados')
                    ->counts('permissions')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuarios con este rol')
                    ->counts('users')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([])
            ->actions([
                EditAction::make()
                    ->visible(fn(\App\Models\Role $record): bool => Auth::user()?->can('update', $record) ?? false),

                DeleteAction::make()
                    ->visible(fn(\App\Models\Role $record): bool => Auth::user()?->can('delete', $record) ?? false)
                    ->before(function (DeleteAction $action, Role $record) {
                        if (in_array($record->name, ['administrador', 'produccion', 'ventas', 'consulta'])) {
                            Notification::make()
                                ->title('No se puede eliminar un rol del sistema')
                                ->danger()
                                ->seconds(5)
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit'   => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    protected static function getPermisosAgrupados(): array
    {
        return [
            'Usuarios' => [
                'ver usuarios',
                'crear usuarios',
                'editar usuarios',
                'eliminar usuarios',
            ],
            'Roles y Permisos' => [
                'ver roles',
                'crear roles',
                'editar roles',
                'eliminar roles',
                'asignar permisos',
            ],
            'Categorías' => [
                'ver categorias',
                'crear categorias',
                'editar categorias',
                'eliminar categorias',
            ],
            'Modelos' => [
                'ver modelos',
                'crear modelos',
                'editar modelos',
                'eliminar modelos',
            ],
            'Productos' => [
                'ver productos',
                'crear productos',
                'editar productos',
                'eliminar productos',
            ],
            'Composiciones' => [
                'ver composiciones',
                'crear composiciones',
                'editar composiciones',
                'eliminar composiciones',
            ],
            'Configuración Zebra' => [
                'ver configuracion zebra',
                'crear configuracion zebra',
                'editar configuracion zebra',
                'eliminar configuracion zebra',
            ],
            'Lotes de Etiquetas' => [
                'ver lotes',
                'crear lotes',
                'generar etiquetas',
                'descargar zpl',
                'descargar pdf etiquetas',
                'anular lotes',
            ],
            'Etiquetas' => [
                'ver etiquetas',
                'descargar zpl individual',
                'descargar pdf individual',
                'anular etiquetas',
            ],
            'Garantías' => [
                'ver garantias',
                'anular garantias',
                'descargar certificado',
                'exportar garantias',
            ],
            'Clientes' => [
                'ver clientes',
                'editar clientes',
            ],
            'Bitácora' => [
                'ver bitacora',
                'consultar seriales',
            ],
        ];
    }
}
