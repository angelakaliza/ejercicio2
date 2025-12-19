<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PedidoCompraResource;
use App\Filament\Resources\SolicitudPagoResource;
use App\Models\CuentaPorPagar;
use App\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PresupuestoPagoProveedores extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Presupuesto de pago a proveedores';

    protected static string $view = 'filament.pages.presupuesto-pago-proveedores';

    protected static ?string $title = 'Presupuesto de pago a proveedores';

    public ?array $data = [];

    public float $selectedProvidersTotal = 0.0;

    public function mount(): void
    {
        $this->form->fill([
            'fecha_desde' => now()->subMonth()->startOfDay(),
            'fecha_hasta' => now()->addMonth()->endOfDay(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Filtros de búsqueda')
                    ->description('Selecciona la conexión, empresas, sucursales y el rango de fechas. El rango se inicializa un mes antes y un mes después de la fecha actual para agilizar la consulta.')
                    ->schema([
                        Forms\Components\Select::make('conexion')
                            ->label('Conexión')
                            ->options(Empresa::query()->pluck('nombre_empresa', 'id'))
                            ->searchable()
                            ->preload()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set): void {
                                $set('empresas', []);
                                $set('sucursales', []);
                            }),
                        Forms\Components\Select::make('empresas')
                            ->label('Empresa')
                            ->multiple()
                            ->options(function (Get $get) {
                                $conexion = $get('conexion');

                                if (! $conexion) {
                                    return [];
                                }

                                $connectionName = PedidoCompraResource::getExternalConnectionName($conexion);

                                if (! $connectionName) {
                                    return [];
                                }

                                try {
                                    return DB::connection($connectionName)
                                        ->table('saeempr')
                                        ->pluck('empr_nom_empr', 'empr_cod_empr')
                                        ->all();
                                } catch (\Exception) {
                                    return [];
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set) => $set('sucursales', [])),
                        Forms\Components\Select::make('sucursales')
                            ->label('Sucursal')
                            ->multiple()
                            ->options(function (Get $get) {
                                $conexion = $get('conexion');
                                $empresas = $get('empresas');

                                if (! $conexion || empty($empresas)) {
                                    return [];
                                }

                                $connectionName = PedidoCompraResource::getExternalConnectionName($conexion);

                                if (! $connectionName) {
                                    return [];
                                }

                                try {
                                    return DB::connection($connectionName)
                                        ->table('saesucu')
                                        ->when(
                                            filled($empresas),
                                            fn ($query) => $query->whereIn('sucu_cod_empr', $empresas),
                                        )
                                        ->pluck('sucu_nom_sucu', 'sucu_cod_sucu')
                                        ->all();
                                } catch (\Exception) {
                                    return [];
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->live(onBlur: true),
                        Forms\Components\DatePicker::make('fecha_desde')
                            ->label('Fecha desde')
                            ->default(now()->subMonth()->startOfDay())
                            ->live(onBlur: true),
                        Forms\Components\DatePicker::make('fecha_hasta')
                            ->label('Fecha hasta')
                            ->default(now()->addMonth()->endOfDay())
                            ->live(onBlur: true),
                    ])
                    ->columns(5),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                return $this->buildBaseQuery();
            })
            ->groups([
                Group::make('empresa_nombre')->label('Empresa')->collapsible(),
                Group::make('sucursal_nombre')->label('Sucursal')->collapsible(),
                Group::make('proveedor_nombre')->label('Proveedor')->collapsible(),
            ])
            ->paginated(false)
            ->recordAction(null)
            ->columns([
                TextColumn::make('dmcp_num_fac')
                    ->label('Factura')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('proveedor_nombre')
                    ->label('Proveedor')
                    ->description(fn ($record): string => $record->proveedor_codigo ?? '')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('empresa_nombre')
                    ->label('Empresa')
                    ->description(fn ($record): string => $record->empresa_codigo ?? '')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sucursal_nombre')
                    ->label('Sucursal')
                    ->description(fn ($record): string => $record->sucursal_codigo ?? '')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dcmp_fec_emis')
                    ->label('Emisión')
                    ->date()
                    ->sortable(),
                TextColumn::make('dmcp_fec_ven')
                    ->label('Vencimiento')
                    ->date()
                    ->sortable(),
                TextColumn::make('dmcp_cod_mone')
                    ->label('Moneda')
                    ->badge(),
                TextColumn::make('saldo')
                    ->label('Saldo (ML)')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('saldo_mext')
                    ->label('Saldo (ME)')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtros adicionales pueden agregarse aquí cuando se definan reglas específicas de negocio.
            ])
            ->headerActions([
                ExportAction::make('export_excel')
                    ->label('Exportar Excel/CSV'),
                Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->color('danger')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (): ?\Symfony\Component\HttpFoundation\StreamedResponse {
                        $records = $this->getSelectedTableRecords();

                        if ($records->isEmpty()) {
                            $records = $this->getFilteredTableQuery()->get();
                        }

                        if ($records->isEmpty()) {
                            return null;
                        }

                        $exportData = $this->prepareExportData($records);

                        return response()->streamDownload(function () use ($exportData) {
                            echo Pdf::loadView('pdfs.presupuesto-pago-proveedores', [
                                'registros' => $exportData,
                                'total' => $exportData->sum('saldo'),
                            ])->stream();
                        }, 'presupuesto-pago-proveedores.pdf');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('exportar_proveedores')
                    ->label('Exportar selección a PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function (Collection $records): ?\Symfony\Component\HttpFoundation\StreamedResponse {
                        if ($records->isEmpty()) {
                            return null;
                        }

                        $exportData = $this->prepareExportData($records);

                        return response()->streamDownload(function () use ($exportData) {
                            echo Pdf::loadView('pdfs.presupuesto-pago-proveedores', [
                                'registros' => $exportData,
                                'total' => $exportData->sum('saldo'),
                            ])->stream();
                        }, 'presupuesto-pago-proveedores-seleccion.pdf');
                    }),
            ])
            ->defaultSort('empresa_nombre');
    }

    public function getTableRecordKey($record): string
    {
        return implode('-', [
            $record->empresa_codigo ?? 'emp',
            $record->sucursal_codigo ?? 'suc',
            $record->proveedor_codigo ?? 'prov',
            $record->dmcp_num_fac ?? $record->getKey(),
        ]);
    }

    public function updatedSelectedTableRecords(): void
    {
        $this->selectedProvidersTotal = $this->getSelectedTableRecords()
            ->sum(fn ($record) => (float) ($record->saldo ?? 0));
    }

    protected function buildBaseQuery(): Builder
    {
        $formData = $this->form->getState();

        if (empty($formData['conexion']) || empty($formData['empresas'])) {
            return (new CuentaPorPagar())->newQuery()->whereRaw('1 = 0');
        }

        $connectionName = PedidoCompraResource::getExternalConnectionName($formData['conexion']);

        if (! $connectionName) {
            return (new CuentaPorPagar())->newQuery()->whereRaw('1 = 0');
        }

        $model = new CuentaPorPagar();
        $model->setConnection($connectionName);

        $query = $model->newQuery()
            ->select([
                'saedmcp.dmcp_cod_tran',
                'saedmcp.dmcp_cod_empr as empresa_codigo',
                DB::raw("COALESCE(empresas.empr_nom_empr, saedmcp.dmcp_cod_empr) as empresa_nombre"),
                'saedmcp.dmcp_cod_sucu as sucursal_codigo',
                DB::raw("COALESCE(sucursales.sucu_nom_sucu, saedmcp.dmcp_cod_sucu) as sucursal_nombre"),
                'saedmcp.clpv_cod_clpv as proveedor_codigo',
                DB::raw("COALESCE(proveedores.clpv_nom_clpv, saedmcp.clpv_cod_clpv) as proveedor_nombre"),
                'saedmcp.dmcp_num_fac',
                'saedmcp.dmcp_cod_mone',
                DB::raw('MIN(dcmp_fec_emis) as dcmp_fec_emis'),
                DB::raw('MAX(dmcp_fec_ven) as dmcp_fec_ven'),
                DB::raw('SUM(COALESCE(dcmp_deb_ml, 0) - COALESCE(dcmp_cre_ml, 0)) as saldo'),
                DB::raw('SUM(COALESCE(dmcp_deb_mext, 0) - COALESCE(dmcp_cre_mext, 0)) as saldo_mext'),
            ])
            ->leftJoin('saeempr as empresas', 'empresas.empr_cod_empr', '=', 'saedmcp.dmcp_cod_empr')
            ->leftJoin('saesucu as sucursales', function ($join) {
                $join->on('sucursales.sucu_cod_empr', '=', 'saedmcp.dmcp_cod_empr')
                    ->on('sucursales.sucu_cod_sucu', '=', 'saedmcp.dmcp_cod_sucu');
            })
            ->leftJoin('saeclpv as proveedores', 'proveedores.clpv_cod_clpv', '=', 'saedmcp.clpv_cod_clpv')
            ->whereIn('saedmcp.dmcp_cod_empr', $formData['empresas'])
            ->where('saedmcp.dmcp_est_dcmp', '<>', 'AN')
            ->groupBy([
                'saedmcp.dmcp_cod_empr',
                'empresa_nombre',
                'saedmcp.dmcp_cod_sucu',
                'sucursal_nombre',
                'saedmcp.clpv_cod_clpv',
                'proveedor_nombre',
                'saedmcp.dmcp_num_fac',
                'saedmcp.dmcp_cod_mone',
                'saedmcp.dmcp_cod_tran',
            ])
            ->orderBy('empresa_nombre')
            ->orderBy('sucursal_nombre')
            ->orderBy('proveedor_nombre')
            ->orderBy('dcmp_fec_emis');

        if (! empty($formData['sucursales'])) {
            $query->whereIn('saedmcp.dmcp_cod_sucu', $formData['sucursales']);
        }

        if (! empty($formData['fecha_desde']) && ! empty($formData['fecha_hasta'])) {
            $query->whereBetween('dcmp_fec_emis', [$formData['fecha_desde'], $formData['fecha_hasta']]);
        }

        return $query;
    }

    protected function prepareExportData(Collection $records): Collection
    {
        return $records->map(function ($record) {
            return [
                'empresa' => $record->empresa_nombre ?? '',
                'sucursal' => $record->sucursal_nombre ?? '',
                'proveedor' => $record->proveedor_nombre ?? '',
                'factura' => $record->dmcp_num_fac ?? '',
                'emision' => $record->dcmp_fec_emis ?? '',
                'vencimiento' => $record->dmcp_fec_ven ?? '',
                'moneda' => $record->dmcp_cod_mone ?? '',
                'saldo' => (float) ($record->saldo ?? 0),
                'saldo_mext' => (float) ($record->saldo_mext ?? 0),
            ];
        });
    }
}
