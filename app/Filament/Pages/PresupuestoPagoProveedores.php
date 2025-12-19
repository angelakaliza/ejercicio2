<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SolicitudPagoResource;
use App\Models\Empresa;
use App\Models\SolicitudPagoDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PresupuestoPagoProveedores extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.presupuesto-pago-proveedores';

    protected static ?string $navigationGroup = 'Solicitudes de Pago y Aprobaciones';

    protected static ?string $title = 'Presupuesto de pago a proveedores';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->form->fill([
            'fecha_desde' => Carbon::now()->subMonth()->startOfDay(),
            'fecha_hasta' => Carbon::now()->addMonth()->endOfDay(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('filters')
            ->schema([
                Forms\Components\Section::make('Filtros del reporte')
                    ->schema([
                        Forms\Components\Select::make('conexion')
                            ->label('Conexión')
                            ->options(Empresa::query()->pluck('nombre_empresa', 'id'))
                            ->searchable()
                            ->preload()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (callable $set, Forms\Get $get, ?int $state) {
                                $empresas = array_keys(SolicitudPagoResource::getEmpresasOptions($state));
                                $set('empresas', $empresas);

                                $sucursales = array_keys(SolicitudPagoResource::getSucursalesOptions($state, $empresas));
                                $set('sucursales', $sucursales);
                            }),
                        Forms\Components\Select::make('empresas')
                            ->label('Empresa')
                            ->multiple()
                            ->options(fn (Forms\Get $get) => SolicitudPagoResource::getEmpresasOptions($get('conexion')))
                            ->default(fn (Forms\Get $get) => array_keys(SolicitudPagoResource::getEmpresasOptions($get('conexion'))))
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (callable $set) => $set('sucursales', [])),
                        Forms\Components\Select::make('sucursales')
                            ->label('Sucursal')
                            ->multiple()
                            ->options(fn (Forms\Get $get) => SolicitudPagoResource::getSucursalesOptions($get('conexion'), $get('empresas') ?? []))
                            ->default(fn (Forms\Get $get) => array_keys(SolicitudPagoResource::getSucursalesOptions($get('conexion'), $get('empresas') ?? [])))
                            ->live(onBlur: true),
                        Forms\Components\DatePicker::make('fecha_desde')
                            ->label('Fecha desde')
                            ->live(onBlur: true),
                        Forms\Components\DatePicker::make('fecha_hasta')
                            ->label('Fecha hasta')
                            ->live(onBlur: true),
                    ])
                    ->columns(5),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->buildQuery())
            ->recordTitleAttribute('proveedor_nombre')
            ->groups([
                Group::make('empresa_label')->label('Empresa')->collapsible(),
                Group::make('sucursal_label')->label('Sucursal')->collapsible(),
                Group::make('proveedor_label')->label('Proveedor')->collapsible(),
            ])
            ->defaultGroup('empresa_label')
            ->columns([
                Stack::make([
                    TextColumn::make('empresa_label')->label('Empresa')->badge(),
                    TextColumn::make('sucursal_label')->label('Sucursal')->badge(),
                    TextColumn::make('proveedor_label')->label('Proveedor')->searchable(),
                    TextColumn::make('numero_factura')->label('Factura')->searchable(),
                ]),
                TextColumn::make('fecha_emision')
                    ->label('Emisión')
                    ->date(),
                TextColumn::make('fecha_vencimiento')
                    ->label('Vencimiento')
                    ->date(),
                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->money('USD')
                    ->sortable(),
            ])
            ->headerActions([
                ExportAction::make('export_excel')->label('Exportar Excel/CSV'),
                Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('danger')
                    ->action(function () {
                        $records = $this->getSelectedTableRecords();
                        if ($records->isEmpty()) {
                            $records = $this->getFilteredTableQuery()->get();
                        }
                        return $this->streamPdf($records);
                    }),
                Action::make('total_seleccionado')
                    ->label(function () {
                        $total = $this->getSelectedProvidersTotal();
                        return 'Total seleccionado: $' . number_format($total, 2, '.', ',');
                    })
                    ->disabled(),
            ])
            ->bulkActions([
                BulkAction::make('exportar_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (Collection $records) => $this->streamPdf($records)),
            ])
            ->paginated(false);
    }

    protected function buildQuery(): Builder
    {
        $filters = $this->form->getState();

        $query = SolicitudPagoDetalle::query()
            ->with('solicitudPago.empresa')
            ->when($filters['conexion'] ?? null, fn (Builder $q, $conexion) => $q->where('id_empresa', $conexion))
            ->when(! empty($filters['empresas']), fn (Builder $q) => $q->whereIn('amdg_id_empresa', $filters['empresas']))
            ->when(! empty($filters['sucursales']), fn (Builder $q) => $q->whereIn('amdg_id_sucursal', $filters['sucursales']))
            ->when(($filters['fecha_desde'] ?? null) && ($filters['fecha_hasta'] ?? null), function (Builder $q) use ($filters) {
                $q->whereBetween('fecha_emision', [$filters['fecha_desde'], $filters['fecha_hasta']]);
            })
            ->select('*');

        return $query;
    }

    protected function mutateTableRecordData(array $data): array
    {
        $empresaNombre = data_get($data, 'solicitudPago.empresa.nombre_empresa', $data['amdg_id_empresa'] ?? '-');
        $sucursalNombre = $data['amdg_id_sucursal'] ?? '-';

        return [
            ...$data,
            'empresa_label' => $empresaNombre,
            'sucursal_label' => $sucursalNombre,
            'proveedor_label' => trim(($data['proveedor_nombre'] ?? $data['proveedor_codigo'] ?? 'Proveedor') . (
                filled($data['proveedor_ruc'] ?? null) ? ' (' . $data['proveedor_ruc'] . ')' : ''
            )),
        ];
    }

    protected function getSelectedProvidersTotal(): float
    {
        return $this->getSelectedTableRecords()
            ->groupBy(fn ($record) => $record->proveedor_codigo)
            ->map(fn ($items) => $items->sum('saldo'))
            ->sum();
    }

    protected function streamPdf(Collection $records)
    {
        if ($records->isEmpty()) {
            return null;
        }

        $mappedRecords = $this->mapRecordsWithLabels($records);

        $grouped = $mappedRecords
            ->groupBy(['empresa_label', 'sucursal_label', 'proveedor_label'])
            ->map(function ($empresa) {
                return $empresa->map(function ($sucursal) {
                    return $sucursal->map(function ($proveedor) {
                        return [
                            'facturas' => $proveedor->values(),
                            'total' => $proveedor->sum('saldo'),
                        ];
                    });
                });
            });

        return response()->streamDownload(function () use ($grouped) {
            echo Pdf::loadView('pdfs.presupuesto-pago-proveedores', [
                'grouped' => $grouped,
            ])->stream();
        }, 'presupuesto-pago-proveedores.pdf');
    }

    protected function mapRecordsWithLabels(Collection $records): Collection
    {
        return $records->map(function (SolicitudPagoDetalle $record) {
            $record->empresa_label = optional($record->solicitudPago?->empresa)->nombre_empresa ?? $record->amdg_id_empresa;
            $record->sucursal_label = $record->amdg_id_sucursal ?? '-';

            $proveedor = $record->proveedor_nombre ?? $record->proveedor_codigo ?? 'Proveedor';
            $ruc = $record->proveedor_ruc ?? null;

            $record->proveedor_label = trim($proveedor . ($ruc ? ' (' . $ruc . ')' : ''));

            return $record;
        });
    }
}
