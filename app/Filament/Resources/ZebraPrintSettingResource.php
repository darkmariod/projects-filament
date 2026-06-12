<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZebraPrintSettingResource\Pages;
use App\Models\ZebraPrintSetting;
use App\Services\ZebraZplService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ZebraPrintSettingResource extends Resource
{
    protected static ?string $model = ZebraPrintSetting::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';
    protected static ?string $navigationLabel = 'Configuración Zebra';
    protected static ?string $modelLabel = 'Configuración Zebra';
    protected static ?string $pluralModelLabel = 'Configuraciones Zebra';
    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', ZebraPrintSetting::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Datos de la impresora')
                    ->schema([
                        Forms\Components\Select::make('connection_type')
                            ->label('Tipo de conexión')
                            ->options([
                                'network' => 'Red (TCP/IP)',
                                'usb'     => 'USB (CUPS)',
                            ])
                            ->default('network')
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la configuración')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('printer_model')
                            ->label('Modelo de impresora')
                            ->default('Zebra ZT411')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Select::make('dpi')
                            ->label('DPI')
                            ->options([
                                203 => '203 DPI',
                                300 => '300 DPI',
                                600 => '600 DPI',
                            ])
                            ->default(203)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                static::recalculateDots($get, $set);
                            })
                            ->hint('Cambiar DPI recalcula los puntos automáticamente'),

                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->default(true),

                        Forms\Components\Toggle::make('show_logo')
                            ->label('Mostrar logo PARAISO')
                            ->default(true)

                    ])->columns(2),

                Section::make('Tamaño de etiqueta')
                    ->description('Los campos en puntos se calculan automáticamente desde mm × DPI / 25.4')
                    ->schema([
                        Forms\Components\TextInput::make('label_width_mm')
                            ->label('Ancho (mm)')
                            ->numeric()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                static::recalculateDots($get, $set);
                            }),

                        Forms\Components\TextInput::make('label_height_mm')
                            ->label('Alto (mm)')
                            ->numeric()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                static::recalculateDots($get, $set);
                            }),

                        Forms\Components\TextInput::make('label_gap_mm')
                            ->label('Separación entre etiquetas (mm)')
                            ->numeric()
                            ->nullable(),

                        Forms\Components\TextInput::make('width_dots')
                            ->label('Ancho en puntos')
                            ->disabled(),

                        Forms\Components\TextInput::make('height_dots')
                            ->label('Alto en puntos')
                            ->disabled(),
                    ])->columns(2),

                Section::make('Ajustes de impresión')
                    ->schema([
                        Forms\Components\TextInput::make('margin_x')
                            ->label('Margen X')
                            ->numeric()
                            ->default(20)
                            ->required(),

                        Forms\Components\TextInput::make('margin_y')
                            ->label('Margen Y')
                            ->numeric()
                            ->default(20)
                            ->required(),

                        Forms\Components\TextInput::make('qr_size')
                            ->label('Tamaño QR')
                            ->numeric()
                            ->default(6)
                            ->required(),

                        Forms\Components\TextInput::make('barcode_height')
                            ->label('Altura código de barras')
                            ->numeric()
                            ->default(120)
                            ->required(),
                    ])->columns(2),

                Section::make('Configuración de conexión')
                    ->description(fn($get) => $get('connection_type') === 'usb'
                        ? 'Impresión por USB usando CUPS. Asegurate de tener la impresora compartida por CUPS en el servidor.'
                        : 'Configuración para imprimir directamente desde el VPS a la Zebra ZT411 por Ethernet. Si no completás estos datos, solo se podrá descargar el ZPL.')
                    ->schema([
                        Forms\Components\TextInput::make('printer_ip')
                            ->label('Dirección IP de la impresora')
                            ->placeholder('Ej: 192.168.1.200')
                            ->maxLength(45)
                            ->hidden(fn($get) => $get('connection_type') !== 'network'),

                        Forms\Components\TextInput::make('printer_port')
                            ->label('Puerto TCP')
                            ->numeric()
                            ->default(9100)
                            ->hidden(fn($get) => $get('connection_type') !== 'network'),

                        Forms\Components\TextInput::make('chunk_size')
                            ->label('Etiquetas por bloque')
                            ->numeric()
                            ->default(500)
                            ->hidden(fn($get) => $get('connection_type') !== 'network'),

                        Forms\Components\TextInput::make('printer_name')
                            ->label('Nombre de la impresora CUPS')
                            ->placeholder('Ej: zebra-zt411')
                            ->maxLength(255)
                            ->hidden(fn($get) => $get('connection_type') !== 'usb'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('connection_type')
                    ->label('Conexión')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'network' => 'info',
                        'usb'     => 'success',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'network' => 'Red',
                        'usb'     => 'USB',
                        default   => $state,
                    }),

                Tables\Columns\TextColumn::make('printer_name')
                    ->label('Impresora USB')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('printer_model')
                    ->label('Impresora')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('dpi')
                    ->label('DPI')
                    ->suffix(' DPI')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('label_width_mm')
                    ->label('Ancho')
                    ->suffix(' mm'),

                Tables\Columns\TextColumn::make('label_height_mm')
                    ->label('Alto')
                    ->suffix(' mm'),

                Tables\Columns\TextColumn::make('qr_size')
                    ->label('QR')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('printer_ip')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('printer_port')
                    ->label('Puerto')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('chunk_size')
                    ->label('Bloque')
                    ->suffix(' etiq.')
                    ->toggleable(),
            ])
            ->filters([])
            ->actions([
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn(ZebraPrintSetting $record): bool => Auth::user()?->can('update', $record) ?? false),

                Action::make('test_print')
                    ->label('Test de impresión')
                    ->icon('heroicon-o-beaker')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Test de impresión Zebra')
                    ->modalDescription('Se enviará una etiqueta de prueba a la impresora para verificar la conexión.')
                    ->action(function (ZebraPrintSetting $record) {
                        $service = new ZebraZplService($record);

                        $w = $record->width_dots;
                        $h = $record->height_dots;
                        $testZpl = "^XA\n"
                            . "^FO0,0^GB{$w},{$h},3,B,0^FS\n"
                            . "^FO20,40^A0N,30,30^FDETIQUETA DE PRUEBA^FS\n"
                            . "^FO20,80^A0N,18,18^FDImpresora: {$record->getPrinterEndpoint()}^FS\n"
                            . "^FO20,110^A0N,18,18^FDSi ves esto, la conexion funciona!^FS\n"
                            . "^FO20,150^GB560,1,3^FS\n"
                            . "^FO20,170^A0N,18,18^FD" . now()->format('d/m/Y H:i:s') . "^FS\n"
                            . "^FO20,200^A0N,18,18^FD{$record->label_width_mm}x{$record->label_height_mm}mm / {$w}x{$h}px - {$record->dpi}DPI^FS\n"
                            . "^XZ";

                        $result = $service->sendToConfiguredPrinter($testZpl);

                        if ($result['success']) {
                            Notification::make()
                                ->title('Test exitoso')
                                ->body("Impresora {$record->getPrinterEndpoint()} responde correctamente")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Error de conexión')
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn(ZebraPrintSetting $record): bool => $record->isAnyPrinterConfigured()),
            ])
            ->bulkActions([]);
    }

    public static function recalculateDots(Forms\Get $get, Forms\Set $set): void
    {
        $dpi = $get('dpi');
        $w = $get('label_width_mm');
        $h = $get('label_height_mm');

        if ($dpi && $w) {
            $set('width_dots', (int) round($w * $dpi / 25.4));
        }
        if ($dpi && $h) {
            $set('height_dots', (int) round($h * $dpi / 25.4));
        }
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListZebraPrintSettings::route('/'),
            'create' => Pages\CreateZebraPrintSetting::route('/create'),
            'edit'   => Pages\EditZebraPrintSetting::route('/{record}/edit'),
        ];
    }
}
