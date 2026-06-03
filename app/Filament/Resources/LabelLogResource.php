<?php

namespace App\Filament\Resources;

use App\Exports\LabelLogsExport;
use App\Filament\Resources\LabelLogResource\Pages;
use App\Models\LabelLog;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class LabelLogResource extends Resource
{
    protected static ?string $model = LabelLog::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Bitácora de etiquetas';
    protected static ?string $modelLabel = 'Registro';
    protected static ?string $pluralModelLabel = 'Bitácora de etiquetas';
    protected static string|\UnitEnum|null $navigationGroup = 'Etiquetas';
    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', LabelLog::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label_serial_display')
                    ->label('Serial')
                    ->getStateUsing(function (LabelLog $record): string {
                        if ($record->label) {
                            $url = LabelResource::getUrl('edit', ['record' => $record->label]);
                            return "<a href='{$url}' class='text-custom-600 dark:text-custom-400 hover:underline'>{$record->label->serial}</a>";
                        }
                        if ($record->labelBatch && $record->labelBatch->serial_from) {
                            $url = LabelBatchResource::getUrl('edit', ['record' => $record->labelBatch]);
                            $range = $record->labelBatch->serial_from
                                . ' → '
                                . $record->labelBatch->serial_to;
                            return "<a href='{$url}' class='text-custom-600 dark:text-custom-400 hover:underline'>{$range}</a>";
                        }
                        return '—';
                    })
                    ->html()
                    ->searchable(query: function ($query, string $search): void {
                        $query->where(function ($q) use ($search) {
                            $q->whereHas('label', fn($q) => $q->where('serial', 'like', "%{$search}%"))
                              ->orWhereHas('labelBatch', fn($q) => $q->where('serial_from', 'like', "%{$search}%"));
                        });
                    }),

                Tables\Columns\TextColumn::make('labelBatch.internal_batch_code')
                    ->label('Lote')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Acción')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'generated'         => 'info',
                        'printed'           => 'success',
                        'printed_network'   => 'success',
                        'printed_queue'     => 'success',
                        'anulled'           => 'danger',
                        'registrar_garantia' => 'warning',
                        default             => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'generated'          => 'Lote generado',
                        'printed'            => 'Marcado como impreso',
                        'printed_network'    => 'Impreso por red',
                        'printed_queue'      => 'Impreso por cola',
                        'anulled'            => 'Anulado',
                        'registrar_garantia' => 'Garantía registrada',
                        default              => $state,
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(80)
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Acción')
                    ->options(fn(): array => LabelLog::distinct()->pluck('action', 'action')->toArray()),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'name'),

                Tables\Filters\Filter::make('date_range')
                    ->label('Fecha')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        if (!empty($data['date_from'])) {
                            $query->whereDate('created_at', '>=', $data['date_from']);
                        }
                        if (!empty($data['date_to'])) {
                            $query->whereDate('created_at', '<=', $data['date_to']);
                        }
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->headerActions([
                Action::make('exportar_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn(): bool => Auth::user()?->can('viewAny', LabelLog::class) ?? false)
                    ->action(function () {
                        Notification::make()
                            ->title('Exportación completada')
                            ->body('Bitácora exportada exitosamente')
                            ->success()
                            ->seconds(5)
                            ->send();

                        return Excel::download(
                            new LabelLogsExport(),
                            'bitacora-' . now()->format('Ymd-His') . '.xlsx'
                        );
                    }),
            ])
            ->paginated([10, 25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLabelLogs::route('/'),
        ];
    }
}
