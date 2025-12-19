<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SolicitudPagoResource;
use App\Models\Empresa;
use App\Models\SolicitudPago;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PresupuestoPagoProveedores extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static string $view = 'filament.pages.presupuesto-pago-proveedores';

    protected static ?string $navigationGroup = 'Solicitudes de Pago y Aprobaciones';

    protected static ?string $title = 'Presupuesto de pago a proveedores';

    protected static ?string $navigationLabel = 'Presupuesto de pago a proveedores';

    public ?array $filters = [];

    public array $facturasDisponibles = [];

    public array $providerTotals = [];

    public array $selectedProviders = [];

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

        $this->selectedProviders = [];
        $this->providerTotals = [];
        $this->facturasDisponibles = [];

        if (! $conexion || empty($empresas)) {
            return;
        }

        $this->facturasDisponibles = $this->buildPresupuesto($conexion, $empresas, $sucursales, $desde, $hasta);
        $this->providerTotals = $this->collectProviderTotals($this->facturasDisponibles);
    }

    protected function collectProviderTotals(array $empresas): array
    {
        return collect($empresas)
            ->flatMap(fn (array $empresa) => $empresa['sucursales'] ?? [])
            ->flatMap(fn (array $sucursal) => $sucursal['proveedores'] ?? [])
            ->mapWithKeys(fn (array $proveedor) => [
                $proveedor['key'] => (float) ($proveedor['total'] ?? 0),
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
        $proveedoresBase = SolicitudPagoResource::getProveedoresBase($empresaId, $empresas, $sucursales);

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
            $proveedorNombre = $row->proveedor_nombre ?? ($proveedoresBase[$empresaCodigo . '|' . $sucursalCodigo . '|' . $proveedorCodigo]['nombre'] ?? $proveedorCodigo);

            if (! isset($agrupado[$empresaCodigo])) {
                $agrupado[$empresaCodigo] = [
                    'empresa_codigo' => $empresaCodigo,
                    'empresa_nombre' => $empresaNombre,
                    'sucursales' => [],
                ];
            }

            if (! isset($agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo])) {
                $agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo] = [
                    'sucursal_codigo' => $sucursalCodigo,
                    'sucursal_nombre' => $sucursalNombre,
                    'proveedores' => [],
                ];
            }

            if (! isset($agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo]['proveedores'][$proveedorCodigo])) {
                $agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo]['proveedores'][$proveedorCodigo] = [
                    'key' => $empresaCodigo . '|' . $sucursalCodigo . '|' . $proveedorCodigo,
                    'proveedor_codigo' => $proveedorCodigo,
                    'proveedor_nombre' => $proveedorNombre,
                    'proveedor_ruc' => $row->proveedor_ruc,
                    'facturas' => [],
                    'total' => 0,
                ];
            }

            $agrupado[$empresaCodigo]['sucursales'][$sucursalCodigo]['proveedores'][$proveedorCodigo]['facturas'][] = [
                'numero' => $row->numero_factura,
                'fecha_emision' => $row->fecha_emision,
                'fecha_vencimiento' => $row->fecha_vencimiento,
                'saldo' => (float) $row->saldo,
            ];
        }

        foreach ($agrupado as &$empresa) {
            foreach ($empresa['sucursales'] as &$sucursal) {
                foreach ($sucursal['proveedores'] as &$proveedor) {
                    $proveedor['facturas'] = collect($proveedor['facturas'])
                        ->sortBy('fecha_emision')
                        ->values()
                        ->all();

                    $proveedor['total'] = collect($proveedor['facturas'])
                        ->sum(fn ($factura) => (float) ($factura['saldo'] ?? 0));
                }
                unset($proveedor);
                $sucursal['proveedores'] = array_values($sucursal['proveedores']);
            }
            unset($sucursal);
            $empresa['sucursales'] = array_values($empresa['sucursales']);
        }
        unset($empresa);

        return array_values($agrupado);
    }

    public function getTotalSeleccionadoProperty(): float
    {
        return collect($this->selectedProviders)
            ->sum(fn (string $key) => $this->providerTotals[$key] ?? 0);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createSolicitudPago')
                ->label('Crear Solicitud de Pago')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->modalWidth('lg')
                ->form([
                    Forms\Components\Placeholder::make('monto_estimado')
                        ->label('Monto estimado (total seleccionado)')
                        ->content(fn () => '$' . number_format($this->totalSeleccionado, 2, '.', ',')),
                    Forms\Components\Textarea::make('motivo')
                        ->label('Motivo de la solicitud')
                        ->rows(3)
                        ->required(),
                    Forms\Components\TextInput::make('monto_aprobado')
                        ->label('Monto aprobado')
                        ->required()
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0.01)
                        ->rule('gt:0'),
                ])
                ->action(fn (array $data) => $this->createSolicitudPago($data)),
            Action::make('exportPdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('danger')
                ->action(fn () => $this->exportPdf()),
            Action::make('exportExcel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(fn () => $this->exportExcel()),
        ];
    }

    protected function ensureSelection(): ?array
    {
        if (empty($this->selectedProviders)) {
            Notification::make()
                ->title('Seleccione al menos un proveedor')
                ->warning()
                ->send();

            return null;
        }

        return $this->getSelectedProviders();
    }

    protected function createSolicitudPago(array $data): void
    {
        $selected = $this->ensureSelection();

        if ($selected === null) {
            return;
        }

        $conexion = $this->filters['conexion'] ?? null;

        if (! $conexion) {
            Notification::make()
                ->title('Seleccione una conexión para crear la solicitud')
                ->warning()
                ->send();

            return;
        }

        $montoEstimado = $this->totalSeleccionado;
        $montoAprobado = (float) ($data['monto_aprobado'] ?? 0);
        $motivo = $data['motivo'] ?? null;

        if ($montoAprobado <= 0) {
            Notification::make()
                ->title('Ingrese un monto aprobado válido')
                ->warning()
                ->send();

            return;
        }

        $empresasSeleccionadas = $this->filters['empresas'] ?? [];
        $sucursalesSeleccionadas = $this->filters['sucursales'] ?? [];
        $primerProveedor = collect($selected)->pluck('proveedor_codigo')->filter()->first();
        $proveedorNombre = collect($selected)->pluck('proveedor_nombre')->filter()->unique()->implode(', ');

        DB::transaction(function () use ($conexion, $empresasSeleccionadas, $sucursalesSeleccionadas, $selected, $montoEstimado, $montoAprobado, $primerProveedor, $proveedorNombre, $motivo) {
            $solicitud = SolicitudPago::create([
                'id_empresa' => $conexion,
                'amdg_id_empresa' => $empresasSeleccionadas[0] ?? '',
                'amdg_id_sucursal' => $sucursalesSeleccionadas[0] ?? null,
                'proveedor_id' => $primerProveedor ?? '',
                'proveedor_nombre' => $proveedorNombre,
                'motivo' => $motivo,
                'fecha' => Carbon::now(),
                'tipo_solicitud' => 'Presupuesto de Pago a Proveedores',
                'empresas_seleccionadas' => $empresasSeleccionadas,
                'sucursales_seleccionadas' => $sucursalesSeleccionadas,
                'proveedores_seleccionados' => $this->selectedProviders,
                'total' => $montoEstimado,
                'monto_estimado' => $montoEstimado,
                'monto_aprobado' => $montoAprobado,
                'aprobado_por_id' => Auth::id(),
                'estado' => 'PENDIENTE',
            ]);

            $detalles = $this->mapDetallesDesdeSeleccion($selected, $conexion);

            if (! empty($detalles)) {
                $solicitud->detalles()->createMany($detalles);
            }
        });

        $this->selectedProviders = [];

        Notification::make()
            ->title('Solicitud de Pago creada')
            ->body('La solicitud se generó con los datos del presupuesto seleccionado.')
            ->success()
            ->send();
    }

    protected function mapDetallesDesdeSeleccion(array $proveedores, int $conexion): array
    {
        return collect($proveedores)
            ->flatMap(function (array $proveedor) use ($conexion) {
                return collect($proveedor['facturas'] ?? [])->map(function (array $factura) use ($conexion, $proveedor) {
                    return [
                        'id_empresa' => $conexion,
                        'amdg_id_empresa' => $proveedor['empresa_codigo'] ?? '',
                        'amdg_id_sucursal' => $proveedor['sucursal_codigo'] ?? null,
                        'proveedor_codigo' => $proveedor['proveedor_codigo'] ?? '',
                        'proveedor_nombre' => $proveedor['proveedor_nombre'] ?? '',
                        'proveedor_ruc' => $proveedor['proveedor_ruc'] ?? null,
                        'numero_factura' => $factura['numero'] ?? '',
                        'fecha_emision' => $factura['fecha_emision'] ?? null,
                        'fecha_vencimiento' => $factura['fecha_vencimiento'] ?? null,
                        'monto' => isset($factura['monto']) ? (float) $factura['monto'] : (float) ($factura['saldo'] ?? 0),
                        'saldo' => (float) ($factura['saldo'] ?? 0),
                    ];
                });
            })
            ->values()
            ->all();
    }

    protected function getSelectedProviders(): array
    {
        return collect($this->facturasDisponibles)
            ->flatMap(fn (array $empresa) => collect($empresa['sucursales'] ?? [])->map(function (array $sucursal) use ($empresa) {
                $sucursal['empresa_codigo'] = $empresa['empresa_codigo'] ?? null;
                $sucursal['empresa_nombre'] = $empresa['empresa_nombre'] ?? null;

                return $sucursal;
            }))
            ->flatMap(function (array $sucursal) {
                return collect($sucursal['proveedores'] ?? [])->map(function (array $proveedor) use ($sucursal) {
                    $proveedor['empresa_codigo'] = $sucursal['empresa_codigo'] ?? null;
                    $proveedor['empresa_nombre'] = $sucursal['empresa_nombre'] ?? null;
                    $proveedor['sucursal_codigo'] = $sucursal['sucursal_codigo'] ?? null;
                    $proveedor['sucursal_nombre'] = $sucursal['sucursal_nombre'] ?? null;

                    return $proveedor;
                });
            })
            ->filter(fn (array $proveedor) => in_array($proveedor['key'], $this->selectedProviders, true))
            ->values()
            ->all();
    }

    protected function exportPdf()
    {
        $selected = $this->ensureSelection();

        if ($selected === null) {
            return null;
        }

        return response()->streamDownload(function () use ($selected) {
            echo Pdf::loadView('pdfs.presupuesto-pago-proveedores', [
                'proveedores' => $selected,
                'total' => $this->totalSeleccionado,
            ])->stream();
        }, 'presupuesto-pago-proveedores.pdf');
    }

    protected function exportExcel()
    {
        $selected = $this->ensureSelection();

        if ($selected === null) {
            return null;
        }

        $rows = [];

        foreach ($selected as $proveedor) {
            foreach ($proveedor['facturas'] as $factura) {
                $rows[] = [
                    'Conexion' => $this->filters['conexion'] ?? '',
                    'Empresa' => $proveedor['empresa_nombre'] ?? $proveedor['empresa_codigo'],
                    'Sucursal' => $proveedor['sucursal_nombre'] ?? $proveedor['sucursal_codigo'],
                    'Proveedor' => $proveedor['proveedor_nombre'] ?? $proveedor['proveedor_codigo'],
                    'RUC' => $proveedor['proveedor_ruc'] ?? '',
                    'Factura' => $factura['numero'] ?? '',
                    'Fecha Emision' => $factura['fecha_emision'] ?? '',
                    'Fecha Vencimiento' => $factura['fecha_vencimiento'] ?? '',
                    'Saldo' => number_format((float) ($factura['saldo'] ?? 0), 2, '.', ''),
                ];
            }
        }

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, array_keys($rows[0] ?? [
                'Conexion' => 'Conexion',
                'Empresa' => 'Empresa',
                'Sucursal' => 'Sucursal',
                'Proveedor' => 'Proveedor',
                'RUC' => 'RUC',
                'Factura' => 'Factura',
                'Fecha Emision' => 'Fecha Emision',
                'Fecha Vencimiento' => 'Fecha Vencimiento',
                'Saldo' => 'Saldo',
            ]));

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'presupuesto-pago-proveedores.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
