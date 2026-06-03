<?php

namespace App\Filament\Resources;

use App\Exports\WarrantiesExport;
use App\Filament\Resources\WarrantyResource\Pages;
use App\Models\Warranty;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class WarrantyResource extends Resource
{
    protected static ?string $model = Warranty::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Garantías';
    protected static ?string $modelLabel = 'Garantía';
    protected static ?string $pluralModelLabel = 'Garantías';
    protected static string|\UnitEnum|null $navigationGroup = 'Garantías';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', Warranty::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Asociación')
                    ->schema([
                        Forms\Components\Select::make('label_id')
                            ->label('Etiqueta')
                            ->relationship('label', 'serial')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente')
                            ->relationship('customer', 'id')
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->full_name)
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])->columns(2),

                Section::make('Datos de la compra')
                    ->schema([
                        Forms\Components\TextInput::make('store_name')
                            ->label('Tienda')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('invoice_number')
                            ->label('N° factura')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Fecha de compra')
                            ->nullable(),
                    ])->columns(2),

                Section::make('Período de garantía')
                    ->schema([
                        Forms\Components\DatePicker::make('warranty_start_date')
                            ->label('Inicio de garantía')
                            ->nullable(),

                        Forms\Components\DatePicker::make('warranty_end_date')
                            ->label('Fin de garantía')
                            ->nullable(),
                    ])->columns(2),

                Section::make('Estado')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'active'  => 'Activa',
                                'expired' => 'Vencida',
                                'anulled' => 'Anulada',
                            ])
                            ->default('active')
                            ->required(),

                        Forms\Components\Toggle::make('terms_accepted')
                            ->label('Términos aceptados')
                            ->default(false),

                        Forms\Components\TextInput::make('pdf_path')
                            ->label('Ruta del certificado PDF')
                            ->nullable()
                            ->maxLength(255),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label.serial')
                    ->label('Serial')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('Cliente')
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer.document_number')
                    ->label('Documento')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('label.product.name')
                    ->label('Producto')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('store_name')
                    ->label('Local')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Factura')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Compra')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('warranty_end_date')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active'    => 'success',
                        'expired'   => 'warning',
                        'anulled'   => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active'    => 'Activa',
                        'expired'   => 'Vencida',
                        'anulled'   => 'Anulada',
                        default     => $state,
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active'  => 'Activa',
                        'expired' => 'Vencida',
                        'anulled' => 'Anulada',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->label('Fecha de registro')
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

                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Producto')
                    ->relationship('label.product', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('quick_search')
                    ->label('Búsqueda rápida')
                    ->form([
                        Forms\Components\TextInput::make('search')
                            ->label('Código / Cliente / Teléfono / Ciudad')
                            ->placeholder('Buscá por código, cliente, teléfono o ciudad...'),
                    ])
                    ->query(function ($query, array $data) {
                        if (!empty($data['search'])) {
                            $s = $data['search'];
                            $query->where(function ($q) use ($s) {
                                $q->whereHas('label', fn($q) => $q->where('serial', 'like', "%{$s}%"))
                                  ->orWhereHas('customer', fn($q) => $q->where('first_name', 'like', "%{$s}%")
                                      ->orWhere('last_name', 'like', "%{$s}%")
                                      ->orWhere('phone', 'like', "%{$s}%")
                                      ->orWhere('city', 'like', "%{$s}%"));
                            });
                        }
                    }),
            ])
            ->headerActions([
                Action::make('exportar_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn(): bool => Auth::user()?->can('export', Warranty::class) ?? false)
                    ->action(function () {
                        $filters = request()->all();

                        return Excel::download(
                            new WarrantiesExport($filters),
                            'garantias-' . now()->format('Ymd-His') . '.xlsx'
                        );
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn(Warranty $record): bool => Auth::user()?->can('view', $record) ?? false),

                Action::make('certificado_pdf')
                    ->label('Certificado PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->visible(fn(Warranty $record): bool => Auth::user()?->can('downloadCertificate', $record) ?? false)
                    ->action(function (Warranty $record) {
                        $label = $record->label->load([
                            'product.productModel',
                            'product.technicalComposition',
                            'labelBatch',
                            'warranty.customer',
                        ]);

                        $pdf = Pdf::loadView('public.certificate-pdf', compact('label'))
                            ->setPaper('a4', 'portrait');

                        Notification::make()
                            ->title('Descargando certificado')
                            ->body("Preparando certificado para la garantía del serial {$record->label->serial}")
                            ->info()
                            ->seconds(5)
                            ->send();

                        return response()->streamDownload(
                            fn() => print($pdf->output()),
                            'certificado-' . $record->label->serial . '.pdf',
                            ['Content-Type' => 'application/pdf']
                        );
                    }),

                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn(Warranty $record): bool =>
                        (Auth::user()?->can('annul', $record) ?? false) &&
                        $record->status === 'active'
                    )
                    ->action(function (Warranty $record) {
                        $record->update(['status' => 'anulled']);

                        Notification::make()
                            ->title('Garantía anulada')
                            ->warning()
                            ->seconds(5)
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWarranties::route('/'),
            'create' => Pages\CreateWarranty::route('/create'),
            'edit'   => Pages\EditWarranty::route('/{record}/edit'),
        ];
    }
}
