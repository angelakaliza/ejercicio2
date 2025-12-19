<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SolicitudPagoResource;
use App\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class PresupuestoPagoProveedores extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.presupuesto-pago-proveedores';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Presupuesto de pago a proveedores';

    /**
     * @var array<string, mixed>
     */
    public array $filters = [
        'conexion' => null,
        'empresas' => [],
        'sucursales' => [],
        'fecha_desde' => null,
        'fecha_hasta' => null,
    ];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $tablaAgrupada = [];

    /**
     * @var array<int, string>
     */
    public array $proveedoresSeleccionados = [];

    public float $totalSeleccionado = 0.0;

    public function mount(): void
    {
        $this->filters['fecha_desde'] = Carbon::now()->subMonth()->startOfDay();
        $this->filters['fecha_hasta'] = Carbon::now()->addMonth()->endOfDay();

        $this->form->fill($this->filters);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('filters')
            ->schema([
                Forms\Components\Section::make('Filtros de búsqueda')
                    ->schema([
                        Forms\Components\Select::make('conexion')
                            ->label('Conexión')
                            ->options(fn (): array => Empresa::query()->pluck('nombre_empresa', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                                $empresas = array_keys(SolicitudPagoResource::getEmpresasOptions((int) $state));
                                $sucursales = SolicitudPagoResource::getSucursalesOptions((int) $state, $empresas);

                                $set('empresas', $empresas);
                                $set('sucursales', array_keys($sucursales));
                                $this->proveedoresSeleccionados = [];
                                $this->cargarDatos();
                            }),
                        Forms\Components\Select::make('empresas')
                            ->label('Empresa')
                            ->multiple()
                            ->options(fn (Get $get): array => SolicitudPagoResource::getEmpresasOptions((int) $get('conexion')))
                            ->preload()
                            ->searchable()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?array $state): void {
                                $empresas = $state ?? [];
                                $sucursales = SolicitudPagoResource::getSucursalesOptions((int) $get('conexion'), $empresas);
                                $set('sucursales', array_keys($sucursales));
                                $this->proveedoresSeleccionados = [];
                                $this->cargarDatos();
                            }),
                        Forms\Components\Select::make('sucursales')
                            ->label('Sucursal')
                            ->multiple()
                            ->options(fn (Get $get): array => SolicitudPagoResource::getSucursalesOptions((int) $get('conexion'), $get('empresas') ?? []))
                            ->preload()
                            ->searchable()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (): void {
                                $this->cargarDatos();
                            }),
                        Forms\Components\DatePicker::make('fecha_desde')
                            ->label('Fecha desde')
                            ->native(false)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn () => $this->cargarDatos()),
                        Forms\Components\DatePicker::make('fecha_hasta')
                            ->label('Fecha hasta')
                            ->native(false)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn () => $this->cargarDatos()),
                    ])
                    ->columns(5),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_pdf')
                ->label('Exportar PDF')
                ->color('danger')
                ->icon('heroicon-o-arrow-down-tray')
                ->disabled(fn (): bool => empty($this->proveedoresSeleccionados))
                ->action(fn () => $this->exportPdf()),
            Action::make('export_excel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-document-text')
                ->disabled(fn (): bool => empty($this->proveedoresSeleccionados))
                ->action(fn () => $this->exportExcel()),
        ];
    }

    public function updatedProveedoresSeleccionados(): void
    {
        $this->calcularTotalSeleccionado();
    }

    public function cargarDatos(): void
    {
        $this->filters = $this->form->getState();

        $this->tablaAgrupada = $this->consultarFacturas();

        $proveedoresDisponibles = $this->collectProveedorKeys($this->tablaAgrupada);

        $this->proveedoresSeleccionados = array_values(array_intersect($this->proveedoresSeleccionados, $proveedoresDisponibles));

        $this->calcularTotalSeleccionado();
    }

    private function consultarFacturas(): array
    {
        $conexion = $this->filters['conexion'] ?? null;
        $empresas = $this->filters['empresas'] ?? [];
        $sucursales = $this->filters['sucursales'] ?? [];
        $fechaDesde = $this->filters['fecha_desde'];
        $fechaHasta = $this->filters['fecha_hasta'];

        if (! $conexion || empty($empresas)) {
            return [];
        }

        $connectionName = SolicitudPagoResource::getExternalConnectionName((int) $conexion);

        if (! $connectionName) {
            return [];
        }

        $empresaOptions = SolicitudPagoResource::getEmpresasOptions((int) $conexion);
        $sucursalOptions = SolicitudPagoResource::getSucursalesOptions((int) $conexion, $empresas);

        try {
            $rows = DB::connection($connectionName)
                ->table('saedmcp')
                ->join('saeclpv as prov', function ($join): void {
                    $join->on('prov.clpv_cod_empr', '=', 'saedmcp.dmcp_cod_empr')
                        ->on('prov.clpv_cod_sucu', '=', 'saedmcp.dmcp_cod_sucu')
                        ->on('prov.clpv_cod_clpv', '=', 'saedmcp.clpv_cod_clpv');
                })
                ->whereIn('saedmcp.dmcp_cod_empr', $empresas)
                ->when(! empty($sucursales), fn ($q) => $q->whereIn('saedmcp.dmcp_cod_sucu', $sucursales))
                ->when($fechaDesde, fn ($q) => $q->whereDate('saedmcp.dcmp_fec_emis', '>=', Carbon::parse($fechaDesde)))
                ->when($fechaHasta, fn ($q) => $q->whereDate('saedmcp.dcmp_fec_emis', '<=', Carbon::parse($fechaHasta)))
                ->where('saedmcp.dmcp_est_dcmp', '<>', 'AN')
                ->selectRaw('
                    saedmcp.dmcp_cod_empr  as empr,
                    saedmcp.dmcp_cod_sucu  as sucu,
                    saedmcp.clpv_cod_clpv  as provcod,
                    prov.clpv_nom_clpv     as provnom,
                    prov.clpv_ruc_clpv     as provruc,
                    saedmcp.dmcp_num_fac   as numfac,
                    MIN(saedmcp.dcmp_fec_emis) AS fecha_emision,
                    MAX(saedmcp.dmcp_fec_ven)  AS fecha_vencimiento,
                    SUM(COALESCE(saedmcp.dcmp_deb_ml,0) - COALESCE(saedmcp.dcmp_cre_ml,0)) as saldo
                ')
                ->groupBy('empr', 'sucu', 'provcod', 'provnom', 'provruc', 'numfac')
                ->havingRaw('SUM(COALESCE(saedmcp.dcmp_deb_ml,0) - COALESCE(saedmcp.dcmp_cre_ml,0)) <> 0')
                ->orderBy('empr')
                ->orderBy('sucu')
                ->orderBy('provnom')
                ->orderBy('fecha_emision')
                ->get();
        } catch (\Throwable) {
            return [];
        }

        $agrupado = [];

        foreach ($rows as $row) {
            $empresaCodigo = $row->empr;
            $sucursalCodigo = $row->sucu;
            $proveedorCodigo = $row->provcod;
            $proveedorKey = $empresaCodigo.'|'.$sucursalCodigo.'|'.$proveedorCodigo;

            if (! isset($agrupado[$empresaCodigo])) {
                $agrupado[$empresaCodigo] = [
                    'codigo' => $empresaCodigo,
                    'nombre' => $empresaOptions[$empresaCodigo] ?? $empresaCodigo,
                    'sucursales' => [],
                ];
            }

            if (! isset($agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo])) {
                $agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo] = [
                    'codigo' => $sucursalCodigo,
                    'nombre' => $sucursalOptions[$sucursalCodigo] ?? $sucursalCodigo,
                    'proveedores' => [],
                ];
            }

            if (! isset($agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo]['proveedores'][$proveedorCodigo])) {
                $agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo]['proveedores'][$proveedorCodigo] = [
                    'codigo' => $proveedorCodigo,
                    'nombre' => $row->provnom ?? $proveedorCodigo,
                    'ruc' => $row->provruc,
                    'key' => $proveedorKey,
                    'facturas' => [],
                ];
            }

            $agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo]['proveedores'][$proveedorCodigo]['facturas'][] = [
                'numero' => $row->numfac,
                'fecha_emision' => $row->fecha_emision,
                'fecha_vencimiento' => $row->fecha_vencimiento,
                'saldo' => (float) $row->saldo,
            ];
        }

        return collect($agrupado)
            ->map(function (array $empresa) {
                $empresa['sucursales'] = collect($empresa['sucursales'])
                    ->map(function (array $sucursal) {
                        $sucursal['proveedores'] = collect($sucursal['proveedores'])
                            ->map(function (array $proveedor) {
                                $proveedor['facturas'] = array_values($proveedor['facturas']);
                                $proveedor['total'] = collect($proveedor['facturas'])->sum('saldo');

                                return $proveedor;
                            })
                            ->values()
                            ->all();

                        return $sucursal;
                    })
                    ->values()
                    ->all();

                return $empresa;
            })
            ->values()
            ->all();
    }

    private function collectProveedorKeys(array $tabla): array
    {
        return collect($tabla)
            ->flatMap(fn (array $empresa) => $empresa['sucursales'] ?? [])
            ->flatMap(fn (array $sucursal) => $sucursal['proveedores'] ?? [])
            ->map(fn (array $proveedor) => $proveedor['key'] ?? '')
            ->filter()
            ->values()
            ->all();
    }

    private function calcularTotalSeleccionado(): void
    {
        $total = 0.0;
        $seleccionados = collect($this->proveedoresSeleccionados)->filter()->flip();

        foreach ($this->tablaAgrupada as $empresa) {
            foreach ($empresa['sucursales'] ?? [] as $sucursal) {
                foreach ($sucursal['proveedores'] ?? [] as $proveedor) {
                    if ($seleccionados->has($proveedor['key'])) {
                        $total += $proveedor['total'] ?? 0;
                    }
                }
            }
        }

        $this->totalSeleccionado = $total;
    }

    private function getSeleccionAgrupada(): array
    {
        $seleccionados = collect($this->proveedoresSeleccionados)->filter()->flip();

        return collect($this->tablaAgrupada)
            ->map(function (array $empresa) use ($seleccionados) {
                $empresa['sucursales'] = collect($empresa['sucursales'] ?? [])
                    ->map(function (array $sucursal) use ($seleccionados) {
                        $sucursal['proveedores'] = collect($sucursal['proveedores'] ?? [])
                            ->filter(fn (array $proveedor) => $seleccionados->has($proveedor['key']))
                            ->values()
                            ->all();

                        return $sucursal;
                    })
                    ->filter(fn (array $sucursal) => count($sucursal['proveedores'] ?? []) > 0)
                    ->values()
                    ->all();

                return $empresa;
            })
            ->filter(fn (array $empresa) => count($empresa['sucursales'] ?? []) > 0)
            ->values()
            ->all();
    }

    private function exportPdf()
    {
        $data = [
            'filtros' => $this->filters,
            'agrupado' => $this->getSeleccionAgrupada(),
            'total' => $this->totalSeleccionado,
        ];

        return response()->streamDownload(function () use ($data): void {
            echo Pdf::loadView('pdfs.presupuesto-pago-proveedores', $data)->stream();
        }, 'presupuesto-pago-proveedores.pdf');
    }

    private function exportExcel()
    {
        $agrupado = $this->getSeleccionAgrupada();

        return response()->streamDownload(function () use ($agrupado): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Empresa', 'Sucursal', 'Proveedor', 'Factura', 'Fecha Emisión', 'Fecha Vencimiento', 'Saldo']);

            foreach ($agrupado as $empresa) {
                foreach ($empresa['sucursales'] ?? [] as $sucursal) {
                    foreach ($sucursal['proveedores'] ?? [] as $proveedor) {
                        foreach ($proveedor['facturas'] ?? [] as $factura) {
                            fputcsv($handle, [
                                $empresa['nombre'] ?? $empresa['codigo'] ?? '',
                                $sucursal['nombre'] ?? $sucursal['codigo'] ?? '',
                                $proveedor['nombre'] ?? $proveedor['codigo'] ?? '',
                                $factura['numero'] ?? '',
                                $factura['fecha_emision'] ?? '',
                                $factura['fecha_vencimiento'] ?? '',
                                $factura['saldo'] ?? 0,
                            ]);
                        }
                    }
                }
            }

            fclose($handle);
        }, 'presupuesto-pago-proveedores.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
