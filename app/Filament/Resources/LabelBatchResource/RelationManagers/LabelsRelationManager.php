<?php

namespace App\Filament\Resources\LabelBatchResource\RelationManagers;

use App\Models\Label;
use App\Models\LabelLog;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class LabelsRelationManager extends RelationManager
{
    protected static string $relationship = 'labels';

    protected static ?string $recordTitleAttribute = 'serial';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('serial')
            ->defaultSort('sequence_number')
            ->columns([
                TextColumn::make('serial')
                    ->label('Serial')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'printed' => 'warning',
                        'registered' => 'info',
                        'anulled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'available' => 'Disponible',
                        'printed' => 'Impreso',
                        'registered' => 'Registrado',
                        'anulled' => 'Anulado',
                        default => $state,
                    }),

                TextColumn::make('printed_at')
                    ->label('Impreso')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),

                TextColumn::make('registered_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'available' => 'Disponible',
                        'printed' => 'Impreso',
                        'registered' => 'Registrado',
                        'anulled' => 'Anulado',
                    ]),

                SelectFilter::make('product_id')
                    ->label('Producto')
                    ->relationship('product', 'name'),
            ])
            ->headerActions([])
            ->actions([
                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Label $record): bool =>
                        (auth()->user()?->can('annul', $record) ?? false)
                        && $record->status !== 'anulled'
                        && $record->status !== 'registered'
                    )
                    ->action(function (Label $record) {
                        if ($record->status === 'registered' && $record->warranty) {
                            Notification::make()
                                ->title('No se puede anular: tiene una garantía registrada')
                                ->danger()
                                ->send();

                            return;
                        }

                        $oldStatus = $record->status;
                        $record->update(['status' => 'anulled']);

                        LabelLog::create([
                            'label_id' => $record->id,
                            'label_batch_id' => $record->label_batch_id,
                            'user_id' => auth()->id(),
                            'action' => 'anulled',
                            'description' => 'Anulado desde lote',
                            'old_data' => ['status' => $oldStatus],
                            'new_data' => ['status' => 'anulled'],
                            'ip' => request()->ip(),
                        ]);

                        Notification::make()
                            ->title('Etiqueta anulada correctamente')
                            ->success()
                            ->send();
                    }),

                Action::make('damaged')
                    ->label('Dañada en prod.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->visible(fn (Label $record): bool =>
                        (auth()->user()?->can('annul', $record) ?? false)
                        && $record->status !== 'anulled'
                        && $record->status !== 'registered'
                    )
                    ->action(function (Label $record) {
                        if ($record->status === 'registered' && $record->warranty) {
                            Notification::make()
                                ->title('No se puede anular: tiene una garantía registrada')
                                ->danger()
                                ->send();

                            return;
                        }

                        $oldStatus = $record->status;
                        $record->update(['status' => 'anulled']);

                        LabelLog::create([
                            'label_id' => $record->id,
                            'label_batch_id' => $record->label_batch_id,
                            'user_id' => auth()->id(),
                            'action' => 'anulled',
                            'description' => 'Dañada en producción',
                            'old_data' => ['status' => $oldStatus],
                            'new_data' => ['status' => 'anulled'],
                            'ip' => request()->ip(),
                        ]);

                        Notification::make()
                            ->title('Etiqueta anulada correctamente')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('bulk_anular')
                    ->label('Dar de baja seleccionadas')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->action(function (Collection $records) {
                        $count = 0;

                        foreach ($records as $record) {
                            if ($record->status === 'anulled' || $record->status === 'registered') {
                                continue;
                            }

                            if ($record->status === 'registered' && $record->warranty) {
                                continue;
                            }

                            $oldStatus = $record->status;
                            $record->update(['status' => 'anulled']);

                            LabelLog::create([
                                'label_id' => $record->id,
                                'label_batch_id' => $record->label_batch_id,
                                'user_id' => auth()->id(),
                                'action' => 'anulled',
                                'description' => 'Anulado desde lote (bulk)',
                                'old_data' => ['status' => $oldStatus],
                                'new_data' => ['status' => 'anulled'],
                                'ip' => request()->ip(),
                            ]);

                            $count++;
                        }

                        Notification::make()
                            ->title("{$count} etiqueta(s) anulada(s) correctamente")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
