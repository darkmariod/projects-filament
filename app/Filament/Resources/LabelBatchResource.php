<?php

namespace App\Filament\Resources;

use App\Exports\LabelBatchesExport;
use App\Filament\Resources\LabelBatchResource\Pages;
use App\Filament\Resources\LabelBatchResource\RelationManagers\LabelsRelationManager;
use App\Models\LabelBatch;
use App\Models\LabelLog;
use App\Models\PrintQueue;
use App\Models\Product;
use App\Models\ZebraPrintSetting;
use App\Services\LabelPdfService;
use App\Services\PrintQueueService;
use App\Services\SerialGeneratorService;
use App\Services\ZebraZplService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
    protected static ?string $navigationLabel = 'Lotes de Etiquetas';
    protected static ?string $modelLabel = 'Lote';
    protected static ?string $pluralModelLabel = 'Lotes de Etiquetas';
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
                Section::make('Crear lote de etiquetas')
                    ->description('Seleccioná el producto y la cantidad. Todo lo demás se genera automáticamente.')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Producto')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (!$state) {
                                    $set('product_info_display', '');
                                    $set('product_measurements_display', '—');
                                    $set('composition_detail_display', '—');
                                    return;
                                }

                                $product = Product::with('technicalComposition', 'productModel')->find($state);
                                if (!$product) {
                                    $set('product_info_display', 'Producto no encontrado');
                                    return;
                                }

                                $parts = [
                                    "Código: {$product->product_code}",
                                    "Medidas: {$product->measurements_text}",
                                ];

                                if ($product->productModel) {
                                    $parts[] = "Modelo: {$product->productModel->name}";
                                }

                                if ($product->technicalComposition) {
                                    $tc = $product->technicalComposition;
                                    if ($tc->commercial_name) {
                                        $parts[] = "Nombre comercial: {$tc->commercial_name}";
                                    }
                                    if ($tc->cover_material) {
                                        $parts[] = "Tapiz: {$tc->cover_material}";
                                    }
                                    if ($tc->foam_description) {
                                        $parts[] = "Espuma: {$tc->foam_description}";
                                    }
                                }

                                $set('product_info_display', implode(' | ', $parts));
                                $set('product_measurements_display', $product->measurements_text ?: '—');

                                $tc = $product->technicalComposition;
                                $model = $product->productModel;
                                if ($tc) {
                                    $set('composition_detail_display', implode(' | ', array_filter([
                                        $tc->product_family ? "Familia: {$tc->product_family}" : null,
                                        $tc->springs ? "Resortes: {$tc->springs}" : null,
                                        $model?->warranty_years ? "Tiempo de garantía: {$model->warranty_years} años" : null,
                                        $tc->general_composition ? "Composición: {$tc->general_composition}" : null,
                                    ])));
                                } else {
                                    $set('composition_detail_display', 'Sin datos de composición técnica');
                                }
                            })
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Cantidad de etiquetas')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(100)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observaciones')
                            ->nullable()
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Información del producto')
                    ->schema([
                        Forms\Components\Placeholder::make('internal_batch_code')
                            ->label('Código interno')
                            ->content(fn () => 'Se generará automáticamente'),

                        Forms\Components\Textarea::make('product_info_display')
                            ->label('Información del producto')
                            ->disabled()
                            ->rows(3)
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('product_measurements_display')
                            ->label('Medidas del producto')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('composition_detail_display')
                            ->label('Detalle de composición')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(1),
                    ])
                    ->columns(2),
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

                // ─── ACCIONES GRUPO: Imprimir ────────────────────────────────
                ActionGroup::make([
                    Action::make('imprimir_zebra')
                        ->label('Imprimir en Zebra')
                        ->icon('heroicon-o-printer')
                        ->color('warning')
                        ->visible(fn(LabelBatch $record): bool =>
                            $record->status !== 'anulled'
                            && $record->labels()->where('status', '!=', 'anulled')->count() > 0
                            && (Auth::user()?->can('create', LabelBatch::class) ?? false)
                        )
                        ->form(function (): array {
                            $activeSetting = ZebraPrintSetting::where('active', true)->first();
                            if ($activeSetting && $activeSetting->isAnyPrinterConfigured()) {
                                return []; // Usa la configuración activa — no hace falta modal
                            }

                            return [
                                Forms\Components\TextInput::make('printer_name')
                                    ->label('Nombre de la impresora en Windows')
                                    ->placeholder('Zebra ZT411')
                                    ->default('Zebra ZT411')
                                    ->helperText('Usá el mismo nombre con el que compartiste la impresora en "Dispositivos e impresoras" → Propiedades → Compartir'),
                            ];
                        })
                        ->modalHeading('Imprimir lote en Zebra ZT411')
                        ->modalDescription(function (LabelBatch $record): string {
                            $total = $record->labels()->where('status', '!=', 'anulled')->count();
                            $activeSetting = ZebraPrintSetting::where('active', true)->first();
                            $printerInfo = $activeSetting?->getPrinterEndpoint() ?? 'configuración manual';
                            return "Se imprimirán {$total} etiquetas en: {$printerInfo}. Cada etiqueta se envía individualmente.";
                        })
                        ->modalSubmitActionLabel('Iniciar impresión')
                        ->action(function (LabelBatch $record, array $data, PrintQueueService $service) {
                            $pendingCount = $record->labels()
                                ->where('status', '!=', 'anulled')
                                ->count();

                            if ($pendingCount === 0) {
                                Notification::make()
                                    ->title('No hay etiquetas pendientes')
                                    ->warning()
                                    ->seconds(5)
                                    ->send();
                                return;
                            }

                            // Evitar colas duplicadas: el lote ya se encola al crearse
                            $activeQueue = PrintQueue::where('label_batch_id', $record->id)
                                ->whereIn('status', ['pending', 'partial', 'processing'])
                                ->first();

                            if ($activeQueue) {
                                Notification::make()
                                    ->title('Este lote ya está en cola de impresión')
                                    ->body("La cola #{$activeQueue->id} ya contiene este lote. El agente la imprimirá automáticamente — no es necesario volver a enviarla.")
                                    ->warning()
                                    ->seconds(10)
                                    ->send();
                                return;
                            }

                            $activeSetting = ZebraPrintSetting::where('active', true)->first();
                            $canUseActiveSetting = $activeSetting && $activeSetting->isAnyPrinterConfigured();

                            if ($canUseActiveSetting) {
                                $ip = $activeSetting->isNetworkConfigured() ? $activeSetting->printer_ip : '';
                                $port = $activeSetting->printer_port ?? 9100;
                                $connectionType = $activeSetting->connection_type;
                                $printerName = $activeSetting->printer_name;
                                $printerInfo = $activeSetting->getPrinterEndpoint();
                            } else {
                                $ip = '';
                                $port = 9100;
                                $connectionType = 'usb';
                                $printerName = $data['printer_name'] ?? 'Zebra ZT411';
                                $printerInfo = $printerName . ' (USB)';
                            }

                            try {
                                $queue = $service->createQueueForBatch(
                                    batch: $record,
                                    ip: $ip,
                                    port: $port,
                                    userId: auth()->id(),
                                    connectionType: $connectionType,
                                    printerName: $printerName,
                                );

                                $labelText = $pendingCount === 1 ? 'etiqueta' : 'etiquetas';
                                $isUsb = $connectionType === 'usb';
                                $msg = "Cola #{$queue->id} creada con {$pendingCount} {$labelText} para {$printerInfo}.";
                                if ($isUsb) {
                                    $msg .= " El agente Windows la procesará automáticamente.";
                                }

                                Notification::make()
                                    ->title('Cola de impresión creada')
                                    ->body($msg)
                                    ->success()
                                    ->seconds(12)
                                    ->send();

                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error al crear la cola')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->seconds(10)
                                    ->send();
                            }
                        }),

                    Action::make('ver_estado_impresion')
                        ->label('Ver estado de impresión')
                        ->icon('heroicon-o-information-circle')
                        ->color('info')
                        ->visible(fn(LabelBatch $record): bool =>
                            $record->printQueue()->exists()
                        )
                        ->modalHeading(fn(LabelBatch $record): string =>
                            'Estado de impresión — Lote ' . $record->internal_batch_code
                        )
                        ->modalWidth('7xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->modalContent(function (LabelBatch $record): string {
                            $queue = $record->printQueue()
                                ->withCount([
                                    'items as total_count',
                                    'items as pending_count' => fn($q) => $q->where('status', 'pending'),
                                    'items as printed_count' => fn($q) => $q->where('status', 'printed'),
                                    'items as failed_count'  => fn($q) => $q->where('status', 'failed'),
                                ])
                                ->latest()
                                ->first();

                            if (!$queue) {
                                return '<p class="text-gray-500">No hay información de impresión.</p>';
                            }

                            $progress = $queue->total_count > 0
                                ? round(($queue->printed_count / $queue->total_count) * 100)
                                : 0;

                            $statusLabel = match ($queue->status) {
                                'pending'    => 'Pendiente',
                                'processing' => 'Procesando',
                                'completed'  => 'Completado',
                                'partial'    => 'Parcial',
                                'failed'     => 'Fallido',
                                'paused'     => 'Pausado',
                                'cancelled'  => 'Cancelado',
                                default      => $queue->status,
                            };

                            $statusBg = match ($queue->status) {
                                'completed'  => '#16a34a',
                                'partial'    => '#ca8a04',
                                'failed'     => '#dc2626',
                                'paused'     => '#ea580c',
                                'processing' => '#2563eb',
                                default      => '#6b7280',
                            };

                            $errorHtml = '';
                            if ($queue->status === 'paused' && $queue->error_message) {
                                $errorHtml = '<div style="margin-top:8px; padding:8px 12px; background:#fff3cd; border:1px solid #ffc107; border-radius:4px; color:#856404; font-size:12px;">'
                                    . '<strong>⏸ Pausado:</strong> ' . e($queue->error_message)
                                    . '</div>';
                            }

                            // ── Encabezado ──
                            $html = '<div style="margin-bottom:16px;">';
                            $html .= '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">';
                            $printerDisplay = $queue->isUsbConnection()
    ? e($queue->printer_name ?? 'USB')
    : e($queue->zebra_ip) . ':' . e((string)$queue->zebra_port);
$html .= '<div><strong>Impresora:</strong> ' . $printerDisplay . '</div>';
                            $html .= '<div><span style="display:inline-block; padding:2px 10px; border-radius:4px; color:white; font-size:12px; font-weight:bold; background:' . $statusBg . ';">' . $statusLabel . '</span></div>';
                            $html .= '</div>';

                            // ── Contadores ──
                            $html .= '<div style="display:flex; gap:16px; margin-bottom:12px; font-size:13px;">';
                            $html .= '<span><strong>Total:</strong> ' . $queue->total_count . '</span>';
                            $html .= '<span style="color:#16a34a;"><strong>Impresas:</strong> ' . $queue->printed_count . '</span>';
                            $html .= '<span style="color:#dc2626;"><strong>Fallidas:</strong> ' . $queue->failed_count . '</span>';
                            $html .= '<span style="color:#6b7280;"><strong>Pendientes:</strong> ' . $queue->pending_count . '</span>';
                            $html .= '</div>';

                            // ── Barra de progreso ──
                            $html .= '<div style="background:#e5e7eb; border-radius:8px; height:12px; overflow:hidden;">';
                            $html .= '<div style="background:#16a34a; height:100%; width:' . $progress . '%; border-radius:8px; transition:width 0.3s;"></div>';
                            $html .= '</div>';
                            $html .= '<div style="text-align:right; font-size:12px; margin-top:2px;">' . $progress . '%</div>';
                            $html .= '</div>';
                            $html .= $errorHtml;

                            // ── Tabla de items ──
                            $items = $queue->items()->with('label')->orderBy('sequence')->get();

                            $html .= '<table style="width:100%; border-collapse:collapse; font-size:12px;">';
                            $html .= '<thead>';
                            $html .= '<tr style="background:#f3f4f6;">';
                            $html .= '<th style="padding:6px 8px; border:1px solid #d1d5db; text-align:left;">#</th>';
                            $html .= '<th style="padding:6px 8px; border:1px solid #d1d5db; text-align:left;">Serial</th>';
                            $html .= '<th style="padding:6px 8px; border:1px solid #d1d5db; text-align:left;">Estado</th>';
                            $html .= '<th style="padding:6px 8px; border:1px solid #d1d5db; text-align:right;">Intentos</th>';
                            $html .= '<th style="padding:6px 8px; border:1px solid #d1d5db; text-align:left;">Error</th>';
                            $html .= '</tr>';
                            $html .= '</thead>';
                            $html .= '<tbody>';

                            foreach ($items as $item) {
                                $itemStatus = match ($item->status) {
                                    'printed'   => '<span style="color:#16a34a; font-weight:bold;">✓ Impresa</span>',
                                    'failed'    => '<span style="color:#dc2626; font-weight:bold;">✗ Fallida</span>',
                                    'pending'   => '<span style="color:#6b7280;">⏳ Pendiente</span>',
                                    'printing'  => '<span style="color:#2563eb;">⟳ Imprimiendo</span>',
                                    'cancelled' => '<span style="color:#6b7280;">— Cancelada</span>',
                                    default     => e($item->status),
                                };
                                $errorText = $item->error_message ? e($item->error_message) : '';
                                $serial    = $item->label?->serial ?? 'N/A';

                                $html .= '<tr>';
                                $html .= '<td style="padding:4px 8px; border:1px solid #d1d5db;">' . $item->sequence . '</td>';
                                $html .= '<td style="padding:4px 8px; border:1px solid #d1d5db; font-family:monospace;">' . e($serial) . '</td>';
                                $html .= '<td style="padding:4px 8px; border:1px solid #d1d5db;">' . $itemStatus . '</td>';
                                $html .= '<td style="padding:4px 8px; border:1px solid #d1d5db; text-align:right;">' . $item->attempts . '/' . $item->max_attempts . '</td>';
                                $html .= '<td style="padding:4px 8px; border:1px solid #d1d5db; color:#dc2626; font-size:11px;">' . $errorText . '</td>';
                                $html .= '</tr>';
                            }

                            $html .= '</tbody>';
                            $html .= '</table>';

                            return $html;
                        })
                        ->modalFooterActions(function (LabelBatch $record, PrintQueueService $service) {
                            $queue = $record->printQueue()->latest()->first();
                            if (!$queue) {
                                return [];
                            }

                            $actions = [];

                            // ⏸ Pausada → Reanudar
                            if ($queue->status === 'paused') {
                                $actions[] = Action::make('resume_print_modal')
                                    ->label('Reanudar impresión')
                                    ->icon('heroicon-o-play')
                                    ->color('success')
                                    ->action(function () use ($record, $service, $queue) {
                                        $service->resume($queue);

                                        Notification::make()
                                            ->title('Impresión reanudada')
                                            ->body('La cola se reanudará automáticamente.')
                                            ->success()
                                            ->seconds(5)
                                            ->send();
                                    });
                            }

                            // 🔁 Partial o failed con fallidas → Reintentar
                            if ($queue->status === 'partial' || ($queue->status === 'failed' && $queue->items()->where('status', 'failed')->exists())) {
                                $actions[] = Action::make('retry_print_modal')
                                    ->label('Reintentar fallidas')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('warning')
                                    ->action(function () use ($record, $service, $queue) {
                                        $service->retryFailed($queue);

                                        Notification::make()
                                            ->title('Reintento encolado')
                                            ->body('Las etiquetas fallidas se reintentarán automáticamente.')
                                            ->success()
                                            ->seconds(5)
                                            ->send();
                                    });
                            }

                            // ✖ Pending o partial → Cancelar pendientes
                            if ($queue->status === 'pending' || $queue->status === 'partial') {
                                $actions[] = Action::make('cancel_print_modal')
                                    ->label('Cancelar pendientes')
                                    ->icon('heroicon-o-x-circle')
                                    ->color('danger')
                                    ->requiresConfirmation()
                                    ->action(function () use ($service, $queue) {
                                        $service->cancelPending($queue);

                                        Notification::make()
                                            ->title('Impresión cancelada')
                                            ->body('Las etiquetas pendientes fueron canceladas.')
                                            ->warning()
                                            ->seconds(5)
                                            ->send();
                                    });
                            }

                            return $actions;
                        }),
                ])
                    ->label('Impresión')
                    ->icon('heroicon-o-printer')
                    ->color('warning')
                    ->visible(fn(LabelBatch $record): bool =>
                        $record->status !== 'anulled'
                    )
                    ->dropdownWidth('md'),

                // ─── ACCIÓN: Generar etiquetas ──────────────────────────────
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

                // ─── ACCIÓN: Marcar como impreso ────────────────────────────
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
                            'status'     => 'printed',
                            'printed_at' => $now,
                        ]);

                        $record->labels()
                            ->whereNull('printed_at')
                            ->where('status', '!=', 'anulled')
                            ->update([
                                'status'     => 'printed',
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

                // ─── ACCIÓN: Revertir impresión ────────────────────────────
                Action::make('revert_print')
                    ->label('Revertir impresión')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn(LabelBatch $record): bool =>
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
                                'batch_id'        => $record->id,
                                'status'          => 'printed',
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

                // ─── ACCIÓN: Descargar ZPL ──────────────────────────────────
                Action::make('descargar_zpl')
                    ->label('Descargar ZPL')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->visible(fn(LabelBatch $record): bool =>
                        $record->status !== 'anulled'
                        && $record->labels()->exists()
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

                // ─── ACCIÓN: Descargar PDF ──────────────────────────────────
                Action::make('descargar_pdf')
                    ->label('Descargar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->visible(fn(LabelBatch $record): bool =>
                        $record->status !== 'anulled'
                        && $record->labels()->exists()
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

                // ─── ACCIÓN: Anular ─────────────────────────────────────────
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

                // ─── ACCIÓN: Editar / Eliminar ─────────────────────────────
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn(LabelBatch $record): bool => Auth::user()?->can('update', $record) ?? false),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn(LabelBatch $record): bool => Auth::user()?->can('delete', $record) ?? false),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            LabelsRelationManager::class,
        ];
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
