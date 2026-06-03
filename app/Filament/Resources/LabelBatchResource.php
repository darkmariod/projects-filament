<?php

namespace App\Filament\Resources;

use App\Exports\LabelBatchesExport;
use App\Filament\Resources\LabelBatchResource\Pages;
use App\Models\LabelBatch;
use App\Models\LabelLog;
use App\Models\PrintQueue;
use App\Models\Product;
use App\Models\User;
use App\Models\ZebraPrintSetting;
use App\Services\LabelPdfService;
use App\Services\SerialGeneratorService;
use App\Services\ZebraZplService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class LabelBatchResource extends Resource
{
    protected static ?string $model = LabelBatch::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Lotes de etiquetas';
    protected static ?string $modelLabel = 'Lote';
    protected static ?string $pluralModelLabel = 'Lotes de etiquetas';
    protected static string|\UnitEnum|null $navigationGroup = 'Etiquetas';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', LabelBatch::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Datos del lote')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Producto')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => Product::where('active', true)->first()?->id),

                        Forms\Components\TextInput::make('internal_batch_code')
                            ->label('Código interno del lote')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Se genera automáticamente si se deja vacío'),

                        Forms\Components\TextInput::make('customer_batch_number')
                            ->label('Número de lote del cliente')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Se genera automáticamente si se deja vacío'),

                        Forms\Components\DatePicker::make('customer_batch_date')
                            ->label('Fecha de lote del cliente')
                            ->required()
                            ->default(now()),
                    ])->columns(2),

                Section::make('Detalles de generación')
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Cantidad')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(100),

                        Forms\Components\TextInput::make('operator')
                            ->label('Operador')
                            ->nullable()
                            ->maxLength(255)
                            ->default(fn () => auth()->user()?->name),

                        Forms\Components\Select::make('generated_by_user_id')
                            ->label('Generado por')
                            ->relationship('generatedBy', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->id()),

                        Forms\Components\DateTimePicker::make('generated_at')
                            ->label('Fecha de generación')
                            ->default(now()),
                    ])->columns(2),

                Section::make('Series')
                    ->schema([
                        Forms\Components\TextInput::make('serial_from')
                            ->label('Serial inicial')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('serial_to')
                            ->label('Serial final')
                            ->nullable()
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Estado y observaciones')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'generated' => 'Generado',
                                'printed'   => 'Impreso',
                                'anulled'   => 'Anulado',
                            ])
                            ->default('generated')
                            ->required(),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observaciones')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('internal_batch_code')
                    ->label('Código interno')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->sortable()
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('operator')
                    ->label('Operador')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('serial_from')
                    ->label('Serial inicial')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('serial_to')
                    ->label('Serial final')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active'    => 'success',
                        'anulled'   => 'danger',
                        'generated' => 'warning',
                        'printed'   => 'info',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active'    => 'Activo',
                        'anulled'   => 'Anulado',
                        'generated' => 'Generado',
                        'printed'   => 'Impreso',
                        default     => $state,
                    }),

                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Generado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function () {
                        $filters = request()->all();
                        return Excel::download(new LabelBatchesExport($filters), 'lotes-' . now()->format('Ymd') . '.xlsx');
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active'    => 'Activo',
                        'anulled'   => 'Anulado',
                        'generated' => 'Generado',
                        'printed'   => 'Impreso',
                    ]),
                Tables\Filters\TernaryFilter::make('trashed')
                    ->label('Eliminados'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn(LabelBatch $record): bool => Auth::user()?->can('update', $record) ?? false),

                Action::make('generar')
                    ->label('Generar etiquetas')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn(LabelBatch $record): bool =>
                        $record->status === 'active'
                        && (Auth::user()?->can('generateLabels', $record) ?? false)
                    )
                    ->action(function (LabelBatch $record) {
                        $service = app(SerialGeneratorService::class);
                        $result = $service->generateLabelsForBatch($record);

                        if (!$result) {
                            Notification::make()
                                ->title('El lote ya tiene etiquetas generadas')
                                ->body('Este lote ya fue generado anteriormente')
                                ->danger()
                                ->seconds(5)
                                ->send();
                            return;
                        }

                        LabelLog::create([
                            'label_batch_id' => $record->id,
                            'user_id'        => auth()->id(),
                            'action'         => 'generated',
                            'description'    => 'Etiquetas generadas para lote ' . $record->internal_batch_code,
                            'ip'             => request()->ip(),
                            'created_at'     => now(),
                        ]);

                        $count = $record->fresh()->labels()->count();

                        Notification::make()
                            ->title('Etiquetas generadas')
                            ->body("Se generaron {$count} etiquetas para el lote {$record->internal_batch_code}")
                            ->success()
                            ->seconds(5)
                            ->send();
                    }),

                Action::make('marcar_impreso')
                    ->label('Marcar como impreso')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->visible(fn(LabelBatch $record): bool =>
                        $record->status === 'generated'
                        && (Auth::user()?->can('downloadZpl', $record) ?? false)
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Marcar lote como impreso')
                    ->modalDescription('¿Confirmás que este lote ya fue impreso? Se marcarán todas las etiquetas pendientes como impresas.')
                    ->action(function (LabelBatch $record) {
                        $now = now();

                        $record->update([
                            'status' => 'printed',
                            'printed_at' => $now,
                        ]);

                        $record->labels()
                            ->whereNull('printed_at')
                            ->where('status', '!=', 'anulled')
                            ->update([
                                'status' => 'printed',
                                'printed_at' => $now,
                            ]);

                        LabelLog::create([
                            'label_batch_id' => $record->id,
                            'user_id'        => auth()->id(),
                            'action'         => 'printed',
                            'description'    => 'Lote marcado como impreso: ' . $record->internal_batch_code,
                            'ip'             => request()->ip(),
                            'created_at'     => $now,
                        ]);

                        Notification::make()
                            ->title('Lote marcado como impreso')
                            ->success()
                            ->seconds(5)
                            ->send();
                    }),

                Action::make('imprimir_zebra')
                    ->label('Imprimir en Zebra (red)')
                    ->icon('heroicon-o-printer')
                    ->color('warning')
                    ->visible(fn(LabelBatch $record): bool =>
                        $record->status === 'generated'
                        && Auth::user()?->can('downloadZpl', $record) ?? false
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Imprimir lote en Zebra ZT411')
                    ->modalDescription(fn(LabelBatch $record): string => self::getPrintModalDescription($record))
                    ->action(function (LabelBatch $record) {
                        $pendingCount = $record->labels()->where('status', '!=', 'anulled')->count();

                        if ($pendingCount === 0) {
                            Notification::make()
                                ->title('No hay etiquetas pendientes')
                                ->warning()
                                ->seconds(5)
                                ->send();
                            return;
                        }

                        $settings = ZebraPrintSetting::where('active', true)->first();

                        if (!$settings || !$settings->isNetworkConfigured()) {
                            Notification::make()
                                ->title('Impresora no configurada')
                                ->body('Configurá la IP de la Zebra ZT411 en Configuración > ZebraPrintSettings')
                                ->danger()
                                ->seconds(8)
                                ->send();
                            return;
                        }

                        $service = new ZebraZplService($settings);

                        Notification::make()
                            ->title('Imprimiendo...')
                            ->body("Enviando {$pendingCount} etiquetas a {$settings->getPrinterEndpoint()}")
                            ->info()
                            ->seconds(5)
                            ->send();

                        // Para lotes grandes (>1000), imprimir chunked
                        // printBatchChunked ya maneja marcado + log internamente
                        if ($pendingCount > 1000) {
                            $result = $service->printBatchChunked(
                                $record,
                                function ($chunk, $total, $printed, $totalLabels) {
                                Notification::make()
                                    ->title("Progreso: {$printed}/{$totalLabels}")
                                    ->body("Chunk {$chunk} de {$total} enviado a la impresora")
                                    ->info()
                                    ->seconds(3)
                                    ->send();
                            },
                                auth()->id(),
                                request()->ip()
                            );
                        } else {
                            // Para lotes chicos, generar todo y enviar
                            $zpl = $service->generateForBatch($record);
                            $result = $service->sendToConfiguredPrinter($zpl);

                            // Marcar etiquetas como impresas SOLO después de confirmar envío exitoso
                            if ($result['success']) {
                                $now = now();
                                $record->labels()
                                    ->where('status', '!=', 'anulled')
                                    ->update(['printed_at' => $now, 'status' => 'printed']);

                                $record->update([
                                    'status'     => 'printed',
                                    'printed_at' => $now,
                                ]);

                                LabelLog::create([
                                    'label_batch_id' => $record->id,
                                    'user_id'        => auth()->id(),
                                    'action'         => 'printed_network',
                                    'description'    => $result['message'],
                                    'ip'             => request()->ip(),
                                    'created_at'     => $now,
                                ]);
                            }
                        }

                        if ($result['success']) {
                            Notification::make()
                                ->title('Impresión completada')
                                ->body($result['message'])
                                ->success()
                                ->seconds(8)
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Error de impresión')
                                ->body($result['message'])
                                ->danger()
                                ->seconds(10)
                                ->send();
                        }
                    }),

                Action::make('enviar_zebra')
                    ->label('Imprimir en Zebra (cola)')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->visible(fn(LabelBatch $record): bool =>
                        $record->status === 'generated'
                        && (Auth::user()?->can('downloadZpl', $record) ?? false)
                        && $record->labels()->count() > 0
                    )
                    ->form([
                        Forms\Components\TextInput::make('zebra_ip')
                            ->label('IP de la Zebra')
                            ->placeholder('192.168.1.200')
                            ->required()
                            ->default(fn() =>
                                ZebraPrintSetting::where('active', true)
                                    ->first()?->printer_ip ?? ''
                            ),
                        Forms\Components\TextInput::make('zebra_port')
                            ->label('Puerto TCP')
                            ->default(9100)
                            ->numeric()
                            ->required(),
                    ])
                    ->action(function (LabelBatch $record, array $data) {
                        $ip   = $data['zebra_ip'];
                        $port = (int) $data['zebra_port'];

                        // 1. Verificar conectividad con la impresora
                        $socket = @fsockopen($ip, $port, $errno, $errstr, 5);
                        if (!$socket) {
                            Notification::make()
                                ->title('Error de conexión')
                                ->body("No se pudo conectar a la impresora en {$ip}:{$port}. Verificá que esté encendida y conectada a la red. ({$errstr})")
                                ->danger()
                                ->seconds(10)
                                ->send();
                            return;
                        }
                        fclose($socket);

                        // 2. Validar códigos de barras de las etiquetas del lote
                        $service = app(ZebraZplService::class);
                        $labels  = $record->labels()
                            ->where('status', '!=', 'anulled')
                            ->with('product:id,barcode')
                            ->get(['id', 'serial']);

                        $barcodeErrors = [];
                        foreach ($labels as $label) {
                            $barcode = $label->product?->barcode ?? '';
                            $errors  = $service->validateBarcode($barcode);
                            if (!empty($errors)) {
                                $barcodeErrors[] = "Serial {$label->serial}: " . implode('; ', $errors);
                            }
                        }

                        if (!empty($barcodeErrors)) {
                            Notification::make()
                                ->title('Códigos de barras inválidos')
                                ->body("Se encontraron errores en los siguientes códigos de barras:\n" . implode("\n", array_slice($barcodeErrors, 0, 10)))
                                ->danger()
                                ->seconds(15)
                                ->send();
                            return;
                        }

                        // 3. Crear el trabajo en la cola
                        $total = $labels->count();

                        PrintQueue::create([
                            'label_batch_id' => $record->id,
                            'user_id'        => auth()->id(),
                            'zebra_ip'       => $ip,
                            'zebra_port'     => $port,
                            'status'         => 'pending',
                            'total_labels'   => $total,
                            'sent_labels'    => 0,
                        ]);

                        Notification::make()
                            ->title('Trabajo agregado a la cola de impresión')
                            ->body("{$total} etiquetas se enviarán a {$ip} en menos de 1 minuto.")
                            ->success()
                            ->seconds(8)
                            ->send();
                    }),

                Action::make('retry_print')
                    ->label('Reintentar impresión')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (LabelBatch $record): bool =>
                        $record->printQueue()->where('status', 'failed')->exists()
                        && (Auth::user()?->can('ver etiquetas') ?? false)
                    )
                    ->action(function (LabelBatch $record) {
                        $failedQueue = $record->printQueue()->where('status', 'failed')->latest()->first();
                        $settings = ZebraPrintSetting::where('active', true)->first();

                        if (!$settings) {
                            Notification::make()
                                ->title('Impresora no configurada')
                                ->body('Configurá la IP de la Zebra ZT411 en Configuración > ZebraPrintSettings')
                                ->danger()
                                ->seconds(8)
                                ->send();
                            return;
                        }

                        $totalLabels = $record->labels()->whereNull('printed_at')->count();

                        $queue = PrintQueue::create([
                            'label_batch_id' => $record->id,
                            'user_id'        => auth()->id(),
                            'zebra_ip'       => $settings->printer_ip,
                            'zebra_port'     => 9100,
                            'status'         => 'pending',
                            'total_labels'   => $totalLabels,
                            'sent_labels'    => 0,
                        ]);

                        LabelLog::create([
                            'label_batch_id' => $record->id,
                            'user_id'        => auth()->id(),
                            'action'         => 'retry_print',
                            'description'    => 'Reintento de impresión para lote ' . $record->internal_batch_code,
                            'old_data'       => ['failed_print_queue_id' => $failedQueue?->id],
                            'new_data'       => ['print_queue_id' => $queue->id],
                            'ip'             => request()->ip(),
                            'created_at'     => now(),
                        ]);

                        Notification::make()
                            ->title('Reintento de impresión encolado')
                            ->body("{$totalLabels} etiquetas se reenviarán a {$settings->printer_ip}.")
                            ->success()
                            ->seconds(5)
                            ->send();
                    }),

                Action::make('revert_print')
                    ->label('Revertir impresión')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (LabelBatch $record): bool =>
                        $record->status === 'printed'
                        && (Auth::user()?->can('anular lotes') ?? false)
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Revertir impresión')
                    ->modalDescription('¿Estás seguro? Vas a marcar este lote como NO impreso. Las etiquetas volverán a estar disponibles.')
                    ->action(function (LabelBatch $record) {
                        $countLabels = $record->labels()->whereNotNull('printed_at')->count();

                        $record->labels()
                            ->whereNotNull('printed_at')
                            ->update(['printed_at' => null, 'status' => 'available']);

                        $record->update(['status' => 'generated', 'printed_at' => null]);

                        LabelLog::create([
                            'label_batch_id' => $record->id,
                            'user_id'        => auth()->id(),
                            'action'         => 'reverted_print',
                            'description'    => 'Impresión revertida para lote ' . $record->internal_batch_code,
                            'old_data'       => [
                                'batch_id'       => $record->id,
                                'status'         => 'printed',
                                'cantidad_labels' => $countLabels,
                            ],
                            'new_data'       => ['status' => 'generated'],
                            'ip'             => request()->ip(),
                            'created_at'     => now(),
                        ]);

                        Notification::make()
                            ->title('Impresión revertida')
                            ->body("El lote {$record->internal_batch_code} volvió a estado generado. {$countLabels} etiquetas están nuevamente disponibles.")
                            ->success()
                            ->seconds(5)
                            ->send();
                    }),

                Action::make('descargar_zpl')
                    ->label('Descargar ZPL')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->visible(fn(LabelBatch $record): bool =>
                        $record->status === 'generated'
                        && (Auth::user()?->can('downloadZpl', $record) ?? false)
                    )
                    ->action(function (LabelBatch $record) {
                        $pendingCount = $record->labels()->where('status', '!=', 'anulled')->count();

                        if ($pendingCount === 0) {
                            Notification::make()
                                ->title('No hay etiquetas pendientes')
                                ->body('Todas las etiquetas de este lote ya fueron impresas')
                                ->warning()
                                ->seconds(5)
                                ->send();
                            return;
                        }

                        $service = app(ZebraZplService::class);
                        $zpl = $service->generateForBatch($record);
                        $filename = $service->getFilenameForBatch($record);

                        Notification::make()
                            ->title('ZPL generado')
                            ->body("{$pendingCount} etiquetas listas para impresión")
                            ->success()
                            ->seconds(5)
                            ->send();

                        return response()->streamDownload(function () use ($zpl) {
                            echo $zpl;
                        }, $filename);
                    }),

                Action::make('descargar_pdf')
                    ->label('Descargar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->visible(fn(LabelBatch $record): bool =>
                        $record->status === 'generated'
                        && (Auth::user()?->can('downloadPdf', $record) ?? false)
                    )
                    ->action(function (LabelBatch $record) {
                        $pendingCount = $record->labels()->where('status', '!=', 'anulled')->count();

                        if ($pendingCount === 0) {
                            Notification::make()
                                ->title('No hay etiquetas pendientes')
                                ->body('Todas las etiquetas de este lote ya fueron impresas')
                                ->warning()
                                ->seconds(5)
                                ->send();
                            return;
                        }

                        $service = app(LabelPdfService::class);
                        $pdf = $service->generateForBatch($record);
                        $filename = $service->getFilenameForBatch($record);

                        Notification::make()
                            ->title('PDF generado')
                            ->body("{$pendingCount} etiquetas listas para impresión")
                            ->success()
                            ->seconds(5)
                            ->send();

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf;
                        }, $filename);
                    }),

                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anular lote')
                    ->modalDescription('¿Estás seguro? Esta acción no se puede deshacer.')
                    ->visible(fn(LabelBatch $record): bool => Auth::user()?->can('annul', $record) ?? false)
                    ->action(function (LabelBatch $record) {
                        $activeWarranties = $record->labels()
                            ->whereHas('warranty', fn($q) => $q->where('status', 'active'))
                            ->count();

                        if ($activeWarranties > 0) {
                            Notification::make()
                                ->title('No se puede anular el lote')
                                ->body("{$activeWarranties} etiqueta(s) tienen garantías activas. Anulalas primero.")
                                ->danger()
                                ->seconds(8)
                                ->send();
                            return;
                        }

                        $oldStatus = $record->status;
                        $record->update(['status' => 'anulled']);

                        if ($record->labels()->count() > 0) {
                            $record->labels()->update(['status' => 'anulled']);
                        }

                        LabelLog::create([
                            'label_batch_id' => $record->id,
                            'user_id'        => auth()->id(),
                            'action'         => 'anulled',
                            'description'    => 'Lote anulado: ' . $record->internal_batch_code,
                            'old_data'       => ['status' => $oldStatus],
                            'new_data'       => ['status' => 'anulled'],
                            'ip'             => request()->ip(),
                            'created_at'     => now(),
                        ]);

                        Notification::make()
                            ->title('Lote anulado exitosamente')
                            ->success()
                            ->seconds(5)
                            ->send();
                    }),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn(LabelBatch $record): bool => Auth::user()?->can('delete', $record) ?? false),
            ])
            ->bulkActions([]);
    }

    public static function getPrintModalDescription(LabelBatch $record): string
    {
        $total = $record->labels()->where('status', '!=', 'anulled')->count();
        $config = ZebraPrintSetting::where('active', true)->first();

        if (!$config || !$config->isNetworkConfigured()) {
            return "⚠️ Hay {$total} etiquetas pendientes, pero la impresora no tiene IP configurada. "
                 . 'Configurala en ZebraPrintSettings antes de imprimir.';
        }

        $label = $total === 1 ? 'etiqueta' : 'etiquetas';
        $chunks = $config->chunk_size > 0 && $total > $config->chunk_size
            ? " (se enviarán en bloques de {$config->chunk_size})"
            : '';

        return "Se imprimirán {$total} {$label} por red hacia {$config->getPrinterEndpoint()}{$chunks}.";
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLabelBatches::route('/'),
            'create' => Pages\CreateLabelBatch::route('/create'),
            'edit'   => Pages\EditLabelBatch::route('/{record}/edit'),
        ];
    }
}
