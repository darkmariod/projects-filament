<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LabelResource\Pages;
use App\Models\Label;
use App\Models\LabelLog;
use App\Exports\LabelsExport;
use App\Services\LabelPdfService;
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

class LabelResource extends Resource
{
    protected static ?string $model = Label::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Etiquetas';
    protected static ?string $modelLabel = 'Etiqueta';
    protected static ?string $pluralModelLabel = 'Etiquetas';
    protected static string|\UnitEnum|null $navigationGroup = 'Etiquetas';
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', Label::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Datos de la etiqueta')
                    ->schema([
                        Forms\Components\Select::make('label_batch_id')
                            ->label('Lote')
                            ->relationship('labelBatch', 'internal_batch_code')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('product_id')
                            ->label('Producto')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('serial')
                            ->label('Serial')
                            ->nullable()
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Identificadores')
                    ->schema([
                        Forms\Components\TextInput::make('sequence_number')
                            ->label('Número de secuencia')
                            ->numeric()
                            ->nullable(),

                        Forms\Components\TextInput::make('barcode')
                            ->label('Código de barras')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('qr_url')
                            ->label('URL del QR')
                            ->nullable()
                            ->maxLength(255)
                            ->helperText('URL que se incrusta en el QR. Se genera automáticamente desde el serial si se deja vacío. Formato: {APP_URL}/p/{serial}'),
                    ])->columns(2),

                Section::make('Estado y fechas')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'available'  => 'Disponible',
                                'anulled'    => 'Anulado',
                                'printed'    => 'Impreso',
                                'registered' => 'Registrado',
                            ])
                            ->default('available')
                            ->required(),

                        Forms\Components\Toggle::make('zpl_generated')
                            ->label('ZPL generado')
                            ->default(false),

                        Forms\Components\DateTimePicker::make('printed_at')
                            ->label('Impreso el'),

                        Forms\Components\DateTimePicker::make('registered_at')
                            ->label('Registrado el'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('labelBatch.internal_batch_code')
                    ->label('Lote')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('serial')
                    ->label('Serial')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\ImageColumn::make('qr_url')
                    ->label('QR')
                    ->size(64)
                    ->square()
                    ->getStateUsing(fn (Label $record): string => route('public.qr.image', $record->serial))
                    ->url(fn (Label $record): string => $record->qr_url)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('barcode')
                    ->label('Código de barras')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('zpl_generated')
                    ->label('ZPL')
                    ->boolean(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'available'  => 'success',
                        'anulled'    => 'danger',
                        'printed'    => 'warning',
                        'registered' => 'info',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'available'  => 'Disponible',
                        'anulled'    => 'Anulado',
                        'printed'    => 'Impreso',
                        'registered' => 'Registrado',
                        default      => $state,
                    }),

                Tables\Columns\TextColumn::make('printed_at')
                    ->label('Impreso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('registered_at')
                    ->label('Registrado')
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
                        return Excel::download(new LabelsExport($filters), 'etiquetas-' . now()->format('Ymd') . '.xlsx');
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'available'  => 'Disponible',
                        'anulled'    => 'Anulado',
                        'printed'    => 'Impreso',
                        'registered' => 'Registrado',
                    ]),
                Tables\Filters\SelectFilter::make('label_batch_id')
                    ->label('Lote')
                    ->relationship('labelBatch', 'internal_batch_code'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn(Label $record): bool => Auth::user()?->can('update', $record) ?? false),

                Action::make('descargar_zpl')
                    ->label('Descargar ZPL')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->visible(fn(Label $record): bool =>
                        $record->status !== 'anulled'
                        && (Auth::user()?->can('downloadZpl', $record) ?? false)
                    )
                    ->action(function (Label $record) {
                        $service = app(ZebraZplService::class);
                        $zpl = $service->generateForLabel($record);
                        $filename = $service->getFilenameForLabel($record);

                        Notification::make()
                            ->title('Descargando etiqueta')
                            ->body("Preparando descarga ZPL para la etiqueta {$record->serial}")
                            ->info()
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
                    ->visible(fn(Label $record): bool =>
                        $record->status !== 'anulled'
                        && (Auth::user()?->can('downloadPdf', $record) ?? false)
                    )
                    ->action(function (Label $record) {
                        $service = app(LabelPdfService::class);
                        $pdf = $service->generateForLabel($record);
                        $filename = $service->getFilenameForLabel($record);

                        Notification::make()
                            ->title('Descargando etiqueta')
                            ->body("Preparando descarga PDF para la etiqueta {$record->serial}")
                            ->info()
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
                    ->visible(fn(Label $record): bool => Auth::user()?->can('annul', $record) ?? false)
                    ->action(function (Label $record) {
                        $warranty = $record->warranty()->where('status', 'active')->first();
                        if ($warranty) {
                            Notification::make()
                                ->title('No se puede anular la etiqueta')
                                ->body("Esta etiqueta tiene una garantía activa. Anulá la garantía primero.")
                                ->danger()
                                ->seconds(8)
                                ->send();
                            return;
                        }

                        $oldStatus = $record->status;
                        $record->update(['status' => 'anulled']);

                        LabelLog::create([
                            'label_id'       => $record->id,
                            'label_batch_id' => $record->label_batch_id,
                            'user_id'        => auth()->id(),
                            'action'         => 'anulled',
                            'description'    => 'Etiqueta anulada: ' . $record->serial,
                            'old_data'       => ['status' => $oldStatus],
                            'new_data'       => ['status' => 'anulled'],
                            'ip'             => request()->ip(),
                            'created_at'     => now(),
                        ]);

                        Notification::make()
                            ->title('Etiqueta anulada')
                            ->body("La etiqueta {$record->serial} fue anulada correctamente")
                            ->warning()
                            ->seconds(5)
                            ->send();
                    }),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn(Label $record): bool => Auth::user()?->can('delete', $record) ?? false),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLabels::route('/'),
            'create' => Pages\CreateLabel::route('/create'),
            'edit'   => Pages\EditLabel::route('/{record}/edit'),
        ];
    }
}
