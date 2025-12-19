<?php

namespace App\Filament\Resources\SolicitudPago2Resource\Pages;

use App\Filament\Resources\SolicitudPago2Resource;
use App\Filament\Resources\SolicitudPagoResource;
use App\Models\Empresa;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateSolicitudPago2 extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SolicitudPago2Resource::class;

    protected static string $view = 'filament.resources.solicitud-pago2-resource.pages.create-solicitud-pago2';

    protected static ?string $title = 'Crear solicitud de pago (facturas)';

    public ?array $filters = [];

    public array $facturasDisponibles = [];

    public array $invoiceTotals = [];

    public array $selectedInvoices = [];

    public float $totalSeleccionado = 0;

    public function mount(): void
    {
        $this->form->fill([
            'fecha_desde' => Carbon::now()->subMonth()->startOfDay(),
            'fecha_hasta' => Carbon::now()->addMonth()->endOfDay(),
        ]);

        $this->loadPresupuesto();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('filters')
            ->schema([
                Section::make('Filtros')
                    ->columns(4)
                    ->schema([
                        Select::make('conexion')
                            ->label('Conexión')
                            ->options(Empresa::query()->pluck('nombre_empresa', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, ?int $state): void {
                                $empresas = array_keys(SolicitudPagoResource::getEmpresasOptions($state));
                                $sucursales = array_keys(SolicitudPagoResource::getSucursalesOptions($state, $empresas));

                                $set('empresas', $empresas);
                                $set('sucursales', $sucursales);

                                $this->loadPresupuesto();
                            }),
                        Select::make('empresas')
                            ->label('Empresa')
                            ->multiple()
                            ->options(fn (Forms\Get $get): array => SolicitudPagoResource::getEmpresasOptions($get('conexion')))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (): void {
                                $this->syncSucursales();
                                $this->loadPresupuesto();
                            }),
                        Select::make('sucursales')
                            ->label('Sucursal')
                            ->multiple()
                            ->options(fn (Forms\Get $get): array => SolicitudPagoResource::getSucursalesOptions($get('conexion'), $get('empresas') ?? []))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (): void {
                                $this->loadPresupuesto();
                            }),
                        DatePicker::make('fecha_desde')
                            ->label('Fecha desde')
                            ->default(Carbon::now()->subMonth()->startOfDay())
                            ->live()
                            ->afterStateUpdated(function (): void {
                                $this->loadPresupuesto();
                            }),
                        DatePicker::make('fecha_hasta')
                            ->label('Fecha hasta')
                            ->default(Carbon::now()->addMonth()->endOfDay())
                            ->live()
                            ->afterStateUpdated(function (): void {
                                $this->loadPresupuesto();
                            }),
                    ]),
            ]);
    }

    protected function syncSucursales(): void
    {
        $conexion = $this->filters['conexion'] ?? null;
        $empresas = $this->filters['empresas'] ?? [];
        $this->filters['sucursales'] = array_keys(SolicitudPagoResource::getSucursalesOptions($conexion, $empresas));
    }

    public function loadPresupuesto(): void
    {
        $conexion = $this->filters['conexion'] ?? null;
        $empresas = $this->filters['empresas'] ?? [];
        $sucursales = $this->filters['sucursales'] ?? [];
        $desde = $this->filters['fecha_desde'] ?? null;
        $hasta = $this->filters['fecha_hasta'] ?? null;

        $this->selectedInvoices = [];
        $this->invoiceTotals = [];
        $this->facturasDisponibles = [];
        $this->totalSeleccionado = 0;

        if (! $conexion || empty($empresas)) {
            return;
        }

        $this->facturasDisponibles = $this->buildPresupuesto($conexion, $empresas, $sucursales, $desde, $hasta);
        $this->invoiceTotals = $this->collectInvoiceTotals($this->facturasDisponibles);
    }

    protected function collectInvoiceTotals(array $empresas): array
    {
        return collect($empresas)
            ->flatMap(fn (array $empresa) => $empresa['sucursales'] ?? [])
            ->flatMap(fn (array $sucursal) => $sucursal['proveedores'] ?? [])
            ->flatMap(fn (array $proveedor) => $proveedor['facturas'] ?? [])
            ->mapWithKeys(fn (array $factura) => [
                $factura['key'] => (float) ($factura['saldo'] ?? 0),
            ])
            ->all();
    }

    protected function buildPresupuesto(int $empresaId, array $empresas, array $sucursales, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $connectionName = SolicitudPagoResource::getExternalConnectionName($empresaId);

        if (! $connectionName) {
            return [];
        }

        $empresasDisponibles = SolicitudPagoResource::getEmpresasOptions($empresaId);
        $sucursalesDisponibles = SolicitudPagoResource::getSucursalesOptions($empresaId, $empresas);

        $query = DB::connection($connectionName)
            ->table('saedmcp')
            ->join('saeclpv as prov', function ($join) {
                $join->on('prov.clpv_cod_empr', '=', 'saedmcp.dmcp_cod_empr')
                    ->on('prov.clpv_cod_sucu', '=', 'saedmcp.dmcp_cod_sucu')
                    ->on('prov.clpv_cod_clpv', '=', 'saedmcp.clpv_cod_clpv');
            })
            ->whereIn('saedmcp.dmcp_cod_empr', $empresas)
            ->when(! empty($sucursales), fn ($q) => $q->whereIn('saedmcp.dmcp_cod_sucu', $sucursales))
            ->where('saedmcp.dmcp_est_dcmp', '<>', 'AN')
            ->selectRaw('
                saedmcp.dmcp_cod_empr   as empresa,
                saedmcp.dmcp_cod_sucu   as sucursal,
                saedmcp.clpv_cod_clpv   as proveedor_codigo,
                prov.clpv_nom_clpv      as proveedor_nombre,
                prov.clpv_ruc_clpv      as proveedor_ruc,
                saedmcp.dmcp_num_fac    as numero_factura,
                MIN(saedmcp.dcmp_fec_emis) as fecha_emision,
                MAX(saedmcp.dmcp_fec_ven)  as fecha_vencimiento,
                SUM(COALESCE(saedmcp.dcmp_deb_ml, 0) - COALESCE(saedmcp.dcmp_cre_ml, 0)) as saldo
            ')
            ->groupBy('saedmcp.dmcp_cod_empr', 'saedmcp.dmcp_cod_sucu', 'saedmcp.clpv_cod_clpv', 'prov.clpv_nom_clpv', 'prov.clpv_ruc_clpv', 'saedmcp.dmcp_num_fac')
            ->havingRaw('SUM(COALESCE(saedmcp.dcmp_deb_ml,0) - COALESCE(saedmcp.dcmp_cre_ml,0)) <> 0');

        if ($fechaDesde && $fechaHasta) {
            $query->whereBetween('saedmcp.dcmp_fec_emis', [$fechaDesde, $fechaHasta]);
        }

        $agrupado = [];

        $registros = $query->get();

        foreach ($registros as $row) {
            $empresaCodigo = $row->empresa;
            $sucursalCodigo = $row->sucursal;
            $proveedorCodigo = $row->proveedor_codigo;

            $empresaNombre = $empresasDisponibles[$empresaCodigo] ?? $empresaCodigo;
            $sucursalNombre = $sucursalesDisponibles[$sucursalCodigo] ?? $sucursalCodigo;

            $facturaKey = $this->makeInvoiceKey($empresaCodigo, $sucursalCodigo, $proveedorCodigo, $row->numero_factura);

            $agrupado[$empresaCodigo]['empresa_codigo'] = $empresaCodigo;
            $agrupado[$empresaCodigo]['empresa_nombre'] = $empresaNombre;

            $agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo]['sucursal_codigo'] = $sucursalCodigo;
            $agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo]['sucursal_nombre'] = $sucursalNombre;

            $agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo]['proveedores'][$proveedorCodigo]['proveedor_codigo'] = $proveedorCodigo;
            $agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo]['proveedores'][$proveedorCodigo]['proveedor_nombre'] = $row->proveedor_nombre;
            $agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo]['proveedores'][$proveedorCodigo]['proveedor_ruc'] = $row->proveedor_ruc;

            $agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo]['proveedores'][$proveedorCodigo]['facturas'][] = [
                'key' => $facturaKey,
                'numero' => $row->numero_factura,
                'fecha_emision' => $row->fecha_emision,
                'fecha_vencimiento' => $row->fecha_vencimiento,
                'saldo' => $row->saldo,
            ];
        }

        return $agrupado;
    }

    protected function makeInvoiceKey(int|string $empresa, int|string $sucursal, int|string $proveedor, int|string $numeroFactura): string
    {
        return sprintf('%s-%s-%s-%s', $empresa, $sucursal, $proveedor, $numeroFactura);
    }

    public function updatedSelectedInvoices(): void
    {
        $this->recalculateTotalSeleccionado();
    }

    protected function recalculateTotalSeleccionado(): void
    {
        $this->totalSeleccionado = collect($this->selectedInvoices)
            ->map(fn (string $key) => $this->invoiceTotals[$key] ?? 0)
            ->sum();
    }
}
