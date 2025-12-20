<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SolicitudPagoResource;
use App\Models\SolicitudPago;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

class SolicitudPagoFacturas extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.solicitud-pago-facturas';

    protected static ?string $title = 'Solicitud de Pagos';

    public ?array $filters = [];

    public array $facturasDisponibles = [];

    public array $selectedInvoices = [];
    public array $openProviders = [];
    public array $invoiceAbonos = [];

    public ?SolicitudPago $solicitud = null;

    public int $perPage = 10;

    public string $search = '';

    public ?string $sortField = 'proveedor_nombre';

    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $recordId = request()->integer('record');
        if ($recordId) {
            $this->solicitud = SolicitudPago::with(['detalles'])->find($recordId);
        }

        if ($this->solicitud) {
            $this->hydrateFromRecord();
        }
        
        if (! $this->solicitud) {
            $this->form->fill([
                'fecha_desde' => Carbon::now()->subYears(5)->startOfDay(),
                'fecha_hasta' => Carbon::now()->endOfDay(),
                'monto_aprobado' => null,
                'motivo' => null,
                'conexiones' => [],
            ]);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function generateReport(): void
    {
        if ($this->solicitud) {
            return;
        }

        $this->resetPage();
        $this->loadFacturas();
    }

    public function getAbonoEnUsoProperty(): float
    {
        return collect($this->invoiceAbonos)->sum(fn($v) => max(0, (float) $v));
    }

    public function getTotalFacturasProperty(): float
    {
        return collect($this->facturasDisponibles)
            ->flatMap(fn(array $proveedor) => collect($proveedor['empresas'] ?? [])->flatMap(fn(array $empresa) => collect($empresa['sucursales'] ?? [])->flatMap(fn(array $sucursal) => collect($sucursal['facturas'] ?? []))))
            ->sum(fn(array $factura) => (float) ($factura['saldo'] ?? 0));
    }


    protected function hydrateFromRecord(): void
    {
        $this->filters = [
            'conexiones' => [$this->solicitud->id_empresa],
            'empresas' => $this->solicitud->empresas_seleccionadas ?? [],
            'sucursales' => $this->solicitud->sucursales_seleccionadas ?? [],
            'fecha_desde' => $this->solicitud->fecha?->copy()->subMonth()->startOfDay(),
            'fecha_hasta' => $this->solicitud->fecha?->copy()->addMonth()->endOfDay(),
            'monto_aprobado' => $this->solicitud->monto_aprobado,
            'motivo' => $this->solicitud->motivo,
        ];

        $this->facturasDisponibles = $this->buildFacturasDesdeSolicitud($this->solicitud);
        $this->selectedInvoices = collect($this->solicitud->detalles ?? [])
            ->map(fn($detalle) => $this->buildFacturaKey(
                $detalle->id_empresa,
                $detalle->amdg_id_empresa,
                $detalle->amdg_id_sucursal,
                $detalle->proveedor_codigo,
                $detalle->numero_factura,
            ))
            ->filter()
            ->values()
            ->all();

        $this->invoiceAbonos = collect($this->solicitud->detalles ?? [])
            ->mapWithKeys(function ($detalle) {
                $key = $this->buildFacturaKey(
                    $detalle->id_empresa,
                    $detalle->amdg_id_empresa,
                    $detalle->amdg_id_sucursal,
                    $detalle->proveedor_codigo,
                    $detalle->numero_factura,
                );

                return [
                    $key => (float) ($detalle->abono ?? $detalle->saldo ?? 0),
                ];
            })
            ->all();

        $this->form->fill($this->filters);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('filters')
            ->schema([
                Section::make('Datos de la solicitud')
                    ->columns(3)
                    ->schema([
                        Select::make('conexiones')
                            ->label('Conexiones')
                            ->multiple()
                            ->options(\App\Models\Empresa::query()->pluck('nombre_empresa', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->disabled(fn() => (bool) $this->solicitud)
                            ->afterStateUpdated(function (Forms\Set $set, ?array $state): void {
                                $empresas = $this->buildDefaultEmpresasSelection($state ?? []);
                                $sucursales = $this->buildDefaultSucursalesSelection($state ?? [], $empresas);

                                $set('empresas', $empresas);
                                $set('sucursales', $sucursales);
                                $this->resetPage();
                                $this->resetFacturasData();
                            }),
                        Select::make('empresas')
                            ->label('Empresa')
                            ->multiple()
                            ->options(fn(Forms\Get $get): array => $this->getEmpresasOptionsByConnections($get('conexiones') ?? []))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->hidden(fn() => (bool) $this->solicitud)
                            ->afterStateUpdated(function (): void {
                                $this->syncSucursales();
                                $this->resetPage();
                                $this->resetFacturasData();
                            }),
                        Select::make('sucursales')
                            ->label('Sucursal')
                            ->multiple()
                            ->options(fn(Forms\Get $get): array => $this->getSucursalesOptionsByConnections(
                                $get('conexiones') ?? [],
                                $this->groupOptionsByConnection($get('empresas') ?? []),
                            ))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->hidden(fn() => (bool) $this->solicitud)
                            ->afterStateUpdated(function (): void {
                                $this->resetPage();
                                $this->resetFacturasData();
                            }),
                    ]),
                Section::make('Filtros de búsqueda')
                    ->hidden(fn() => (bool) $this->solicitud)
                    ->columns(4)
                    ->schema([
                        DatePicker::make('fecha_desde')
                            ->label('Fecha desde')
                            ->default(Carbon::now()->subYears(5)->startOfDay())
                            ->live()
                            ->afterStateUpdated(function (): void {
                                $this->resetPage();
                                $this->resetFacturasData();
                            }),
                        DatePicker::make('fecha_hasta')
                            ->label('Fecha hasta')
                            ->default(Carbon::now()->endOfDay())
                            ->live()
                            ->afterStateUpdated(function (): void {
                                $this->resetPage();
                                $this->resetFacturasData();
                            }),
                        Actions::make([
                            FormAction::make('generateReport')
                                ->label('Generar reporte')
                                ->icon('heroicon-o-document-arrow-down')
                                ->color('primary')
                                ->action(fn() => $this->generateReport()),
                        ])->columnSpan(1),
                    ]),
                Section::make('Resumen y aprobación')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('monto_estimado')
                            ->label('Monto estimado (total seleccionado)')
                            ->content(fn() => '$' . number_format($this->totalSeleccionado, 2, '.', ',')),
                        TextInput::make('monto_aprobado')
                            ->label('Monto aprobado')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0.01)
                            ->default(fn() => $this->filters['monto_aprobado'] ?? null),
                        Textarea::make('motivo')
                            ->label('Comentario / Motivo')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Ingrese el motivo o comentario de la solicitud de pago')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function syncSucursales(): void
    {
        $conexiones = $this->filters['conexiones'] ?? [];
        $empresas = $this->filters['empresas'] ?? [];

        $this->filters['sucursales'] = $this->buildDefaultSucursalesSelection($conexiones, $empresas);
    }

    protected function resetFacturasData(): void
    {
        $this->selectedInvoices = [];
        $this->invoiceAbonos = [];
        $this->facturasDisponibles = [];
        $this->openProviders = [];
    }

    public function loadFacturas(): void
    {
        if ($this->solicitud) {
            return;
        }

        $conexiones = $this->filters['conexiones'] ?? [];
        $empresas = $this->groupOptionsByConnection($this->filters['empresas'] ?? []);
        $sucursales = $this->groupOptionsByConnection($this->filters['sucursales'] ?? []);
        $desde = $this->filters['fecha_desde'] ?? null;
        $hasta = $this->filters['fecha_hasta'] ?? null;

        $this->resetFacturasData();

        if (empty($conexiones)) {
            return;
        }

        $this->facturasDisponibles = $this->buildFacturas($conexiones, $empresas, $sucursales, $desde, $hasta);
    }

    public function getTotalSeleccionadoProperty(): float
    {
        $selected = collect($this->getSelectedInvoices());

        return $selected->sum(fn(array $factura) => (float) ($factura['abono'] ?? 0));
    }

    public function getPresupuestoDisponibleProperty(): float
    {
        $aprobado = (float) ($this->filters['monto_aprobado'] ?? 0);

        return max(0, $aprobado - $this->abonoEnUso);
    }


    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver')
                ->label('Volver al listado')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(SolicitudPagoResource::getUrl()),
            Action::make('guardarBorrador')
                ->label('Guardar borrador')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->action(fn() => $this->guardarSolicitud('PENDIENTE')),
            Action::make('aprobarSolicitud')
                ->label('Aprobar y enviar')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->action(fn() => $this->guardarSolicitud('APROBADO')),
        ];
    }

    protected function guardarSolicitud(string $estado = 'PENDIENTE'): void
    {
        $montoAprobado = (float) ($this->filters['monto_aprobado'] ?? $this->totalSeleccionado ?? 0);

        if ($estado === 'APROBADO' && $montoAprobado <= 0) {
            Notification::make()
                ->title('Ingrese un monto aprobado válido')
                ->warning()
                ->send();

            return;
        }

        if ($estado === 'APROBADO' && $this->totalSeleccionado <= 0) {
            Notification::make()
                ->title('Ingrese un abono para al menos una factura')
                ->warning()
                ->send();

            return;
        }

        $selected = $this->getSelectedInvoices();

        if (empty($selected)) {
            Notification::make()
                ->title('Seleccione al menos una factura')
                ->warning()
                ->send();

            return;
        }

        $conexion = $this->solicitud?->id_empresa ?? collect($this->filters['conexiones'] ?? [])->first();

        if (! $conexion) {
            Notification::make()
                ->title('Seleccione una conexión para guardar la solicitud')
                ->warning()
                ->send();

            return;
        }

        $montoEstimado = $this->totalSeleccionado;

        if ($estado === 'APROBADO' && $montoEstimado > $montoAprobado) {
            Notification::make()
                ->title('El abono supera el monto aprobado')
                ->body('Ajuste los valores de abono o incremente el monto aprobado para continuar.')
                ->warning()
                ->send();

            return;
        }

        DB::transaction(function () use ($conexion, $selected, $montoEstimado, $montoAprobado, $estado) {
            $empresasSeleccionadas = $this->groupOptionsByConnection($this->filters['empresas'] ?? []);
            $sucursalesSeleccionadas = $this->groupOptionsByConnection($this->filters['sucursales'] ?? []);
            $primerProveedor = collect($selected)->pluck('proveedor_codigo')->filter()->first();
            $proveedorNombre = collect($selected)->pluck('proveedor_nombre')->filter()->unique()->implode(', ');

            $payload = [
                'id_empresa' => $conexion,
                'amdg_id_empresa' => collect($empresasSeleccionadas)->flatten()->first() ?? '',
                'amdg_id_sucursal' => collect($sucursalesSeleccionadas)->flatten()->first() ?? null,
                'proveedor_id' => $primerProveedor ?? '',
                'proveedor_nombre' => $proveedorNombre,
                'fecha' => $this->solicitud?->fecha ?? Carbon::now(),
                'tipo_solicitud' => 'Pago de Facturas',
                'empresas_seleccionadas' => $empresasSeleccionadas,
                'sucursales_seleccionadas' => $sucursalesSeleccionadas,
                'proveedores_seleccionados' => collect($selected)->map(fn(array $factura) => $factura['proveedor_key'] ?? null)->filter()->unique()->values()->all(),
                'total' => $montoEstimado,
                'monto_estimado' => $montoEstimado,
                'monto_aprobado' => $montoAprobado,
                'monto_utilizado' => $montoEstimado,
                'motivo' => $this->filters['motivo'] ?? null,
                'aprobado_por_id' => Auth::id(),
                'estado' => $estado,
            ];

            if ($this->solicitud) {
                $this->solicitud->update($payload);
                $this->solicitud->detalles()->delete();
                $solicitud = $this->solicitud;
            } else {
                $solicitud = SolicitudPago::create($payload);
                $this->solicitud = $solicitud;
            }

            $detalles = $this->mapDetallesDesdeSeleccion($selected, $conexion);

            if (! empty($detalles)) {
                $solicitud->detalles()->createMany($detalles);
            }
        });

        if ($this->solicitud) {
            $this->solicitud->refresh(['detalles']);
            $this->hydrateFromRecord();
        }

        $this->selectedInvoices = $this->solicitud ? $this->selectedInvoices : [];

        Notification::make()
            ->title($this->solicitud ? 'Solicitud de Pago guardada' : 'Solicitud de Pago creada')
            ->body($estado === 'APROBADO' ? 'La solicitud fue aprobada y enviada.' : 'La solicitud quedó guardada como borrador.')
            ->success()
            ->send();
    }

    protected function mapDetallesDesdeSeleccion(array $facturas, int $conexion): array
    {
        return collect($facturas)
            ->map(function (array $factura) use ($conexion) {
                $abono = (float) ($factura['abono'] ?? $factura['saldo'] ?? 0);
                $saldo = (float) ($factura['saldo'] ?? 0);
                $total = (float) ($factura['total'] ?? $factura['monto'] ?? $saldo);

                return [
                    'id_empresa' => $factura['conexion_id'] ?? $conexion,
                    'amdg_id_empresa' => $factura['empresa_codigo'] ?? '',
                    'amdg_id_sucursal' => $factura['sucursal_codigo'] ?? null,
                    'proveedor_codigo' => $factura['proveedor_codigo'] ?? '',
                    'proveedor_nombre' => $factura['proveedor_nombre'] ?? '',
                    'proveedor_ruc' => $factura['proveedor_ruc'] ?? null,
                    'numero_factura' => $factura['numero'] ?? '',
                    'fecha_emision' => $factura['fecha_emision'] ?? null,
                    'fecha_vencimiento' => $factura['fecha_vencimiento'] ?? null,
                    'monto' => isset($factura['monto']) ? (float) $factura['monto'] : $saldo,
                    'total' => $total,
                    'saldo' => $saldo,
                    'abono' => $abono,
                    'saldo_pendiente' => max(0, $saldo - $abono),
                    'estado_abono' => $this->resolveEstadoAbono($total, $abono),
                ];
            })
            ->values()
            ->all();
    }

    protected function resolveEstadoAbono(float $total, float $abono): string
    {
        $total = max(0, $total);
        $abono = max(0, $abono);

        if ($abono <= 0) {
            return 'SIN_ABONO';
        }

        if ($total > 0 && $abono >= $total) {
            return 'ABONADO_TOTAL';
        }

        return 'ABONADO_PARCIAL';
    }

    protected function getSelectedInvoices(): array
    {
        $selectedKeys = collect($this->selectedInvoices);

        return collect($this->facturasDisponibles)
            ->flatMap(function (array $proveedor) {
                return collect($proveedor['empresas'] ?? [])->flatMap(function (array $empresa) use ($proveedor) {
                    return collect($empresa['sucursales'] ?? [])->flatMap(function (array $sucursal) use ($proveedor, $empresa) {
                        return collect($sucursal['facturas'] ?? [])->map(function (array $factura) use ($proveedor, $empresa, $sucursal) {
                            $abono = $this->resolveAbono($factura);

                            return array_merge($factura, [
                                'proveedor_key' => $proveedor['key'] ?? null,
                                'proveedor_codigo' => $proveedor['proveedor_codigo'] ?? null,
                                'proveedor_nombre' => $proveedor['proveedor_nombre'] ?? null,
                                'proveedor_ruc' => $proveedor['proveedor_ruc'] ?? null,
                                'empresa_codigo' => $empresa['empresa_codigo'] ?? null,
                                'empresa_nombre' => $empresa['empresa_nombre'] ?? null,
                                'sucursal_codigo' => $sucursal['sucursal_codigo'] ?? null,
                                'sucursal_nombre' => $sucursal['sucursal_nombre'] ?? null,
                                'abono' => $abono,
                                'saldo_pendiente' => max(0, (float) ($factura['saldo'] ?? 0) - $abono),
                            ]);
                        });
                    });
                });
            })
            ->filter(fn(array $factura) => $selectedKeys->contains($factura['key'] ?? null))
            ->values()
            ->all();
    }

    public function updatedSelectedInvoices(): void
    {
        $selectedKeys = collect($this->selectedInvoices)->values();
        $keysIndex = $selectedKeys->flip()->all();

        foreach ($selectedKeys as $key) {
            if (! isset($this->invoiceAbonos[$key])) {
                $factura = $this->findFacturaByKey($key);
                $saldo = (float) ($factura['saldo'] ?? 0);
                $maxPermitido = min($saldo, $this->presupuestoDisponible);
                $this->invoiceAbonos[$key] = max(0, $maxPermitido);
            }
        }

        $this->invoiceAbonos = array_intersect_key($this->invoiceAbonos, $keysIndex);
    }

    public function updatedInvoiceAbonos($value, string $key): void
    {
        $factura = $this->findFacturaByKey($key);
        $saldoFactura = (float) ($factura['saldo'] ?? 0);

        // Normaliza: permite "1,23" y strings vacíos mientras escribe
        if ($value === '' || $value === null) {
            $this->invoiceAbonos[$key] = 0;
            return;
        }

        $raw = is_string($value) ? str_replace(',', '.', $value) : $value;
        $numero = (float) $raw;

        $ajustado = $this->resolveAbonoPermitido($key, $numero, $saldoFactura);

        // Redondeo para evitar números raros por float
        $this->invoiceAbonos[$key] = round($ajustado, 2);
    }


    protected function resolveAbono(array $factura): float
    {
        $key = $factura['key'] ?? null;
        if (! $key) {
            return 0;
        }

        $saldoFactura = (float) ($factura['saldo'] ?? 0);

        $ingresado = (float) ($this->invoiceAbonos[$key] ?? 0);

        return $this->resolveAbonoPermitido($key, $ingresado, $saldoFactura);
    }

    protected function resolveAbonoPermitido(string $key, float $valorIngresado, float $saldoFactura): float
    {
        $ingresado = max(0, $valorIngresado);

        $totalSinEsta = collect($this->invoiceAbonos)
            ->except($key)
            ->sum(fn($v) => max(0, (float) $v));

        $montoAprobado = (float) ($this->filters['monto_aprobado'] ?? 0);
        $disponible = max(0, $montoAprobado - $totalSinEsta);

        $maxPermitido = min($saldoFactura, $disponible);

        $abonoFinal = min($ingresado, $maxPermitido);
        $abonoFinal = round($abonoFinal, 2);

        $this->invoiceAbonos[$key] = $abonoFinal;

        return $abonoFinal;
    }



    protected function findFacturaByKey(string $key): array
    {
        return collect($this->facturasDisponibles)
            ->flatMap(fn(array $proveedor) => collect($proveedor['empresas'] ?? [])->flatMap(fn(array $empresa) => collect($empresa['sucursales'] ?? [])->flatMap(fn(array $sucursal) => collect($sucursal['facturas'] ?? []))))
            ->firstWhere('key', $key) ?? [];
    }

    protected function buildFacturas(array $conexiones, array $empresasSeleccionadas, array $sucursalesSeleccionadas, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $connectionNames = \App\Models\Empresa::query()->pluck('nombre_empresa', 'id');

        $registros = collect();

        foreach ($conexiones as $conexion) {
            $empresas = $empresasSeleccionadas[$conexion] ?? array_keys(SolicitudPagoResource::getEmpresasOptions($conexion));

            if (empty($empresas)) {
                continue;
            }

            $sucursales = $sucursalesSeleccionadas[$conexion] ?? [];
            $registros = $registros->merge($this->fetchInvoices($conexion, $empresas, $sucursales, $fechaDesde, $fechaHasta, $connectionNames[$conexion] ?? ''));
        }

        return $this->groupByProveedor($registros);
    }

    protected function fetchInvoices(int $conexion, array $empresas, array $sucursales, ?string $fechaDesde, ?string $fechaHasta, string $conexionNombre): array
    {
        $connectionName = SolicitudPagoResource::getExternalConnectionName($conexion);

        if (! $connectionName) {
            return [];
        }

        $empresasDisponibles = SolicitudPagoResource::getEmpresasOptions($conexion);
        $sucursalesDisponibles = SolicitudPagoResource::getSucursalesOptions($conexion, $empresas);
        $proveedoresBase = SolicitudPagoResource::getProveedoresBase($conexion, $empresas, $sucursales);

        $query = DB::connection($connectionName)
            ->table('saedmcp')
            ->join('saeclpv as prov', function ($join) {
                $join->on('prov.clpv_cod_empr', '=', 'saedmcp.dmcp_cod_empr')
                    ->on('prov.clpv_cod_sucu', '=', 'saedmcp.dmcp_cod_sucu')
                    ->on('prov.clpv_cod_clpv', '=', 'saedmcp.clpv_cod_clpv');
            })
            ->whereIn('saedmcp.dmcp_cod_empr', $empresas)
            ->when(! empty($sucursales), fn($q) => $q->whereIn('saedmcp.dmcp_cod_sucu', $sucursales))
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
                ABS(SUM(
                        COALESCE(saedmcp.dcmp_deb_ml, 0)
                        - COALESCE(saedmcp.dcmp_cre_ml, 0)
                    )) as saldo
                 ')
            ->groupBy('saedmcp.dmcp_cod_empr', 'saedmcp.dmcp_cod_sucu', 'saedmcp.clpv_cod_clpv', 'prov.clpv_nom_clpv', 'prov.clpv_ruc_clpv', 'saedmcp.dmcp_num_fac')
            ->havingRaw('SUM(COALESCE(saedmcp.dcmp_deb_ml,0) - COALESCE(saedmcp.dcmp_cre_ml,0)) <> 0');

        if ($fechaDesde && $fechaHasta) {
            $query->whereBetween('saedmcp.dcmp_fec_emis', [$fechaDesde, $fechaHasta]);
        }

        return $query->get()
            ->map(function ($row) use ($conexion, $conexionNombre, $empresasDisponibles, $sucursalesDisponibles, $proveedoresBase) {
                $empresaCodigo = $row->empresa;
                $sucursalCodigo = $row->sucursal;

                return [
                    'conexion_id' => $conexion,
                    'conexion_nombre' => $conexionNombre,
                    'empresa_codigo' => $empresaCodigo,
                    'empresa_nombre' => $empresasDisponibles[$empresaCodigo] ?? $empresaCodigo,
                    'sucursal_codigo' => $sucursalCodigo,
                    'sucursal_nombre' => $sucursalesDisponibles[$sucursalCodigo] ?? $sucursalCodigo,
                    'proveedor_codigo' => $row->proveedor_codigo,
                    'proveedor_nombre' => $row->proveedor_nombre ?? ($proveedoresBase[$empresaCodigo . '|' . $sucursalCodigo . '|' . $row->proveedor_codigo]['nombre'] ?? $row->proveedor_codigo),
                    'proveedor_ruc' => $row->proveedor_ruc,
                    'numero' => $row->numero_factura,
                    'fecha_emision' => $row->fecha_emision,
                    'fecha_vencimiento' => $row->fecha_vencimiento,
                    'total' => abs((float) $row->saldo),
                    'saldo' => abs((float) $row->saldo),
                ];
            })
            ->all();
    }

    protected function groupByProveedor($registros): array
    {
        $agrupado = [];

        foreach ($registros as $row) {
            $proveedorKey = $this->buildProveedorKey($row['proveedor_codigo'] ?? '', $row['proveedor_ruc'] ?? '', $row['proveedor_nombre'] ?? '');
            $empresaKey = ($row['conexion_id'] ?? '') . '|' . ($row['empresa_codigo'] ?? '');
            $sucursalKey = $empresaKey . '|' . ($row['sucursal_codigo'] ?? '');

            if (! isset($agrupado[$proveedorKey])) {
                $agrupado[$proveedorKey] = [
                    'key' => $proveedorKey,
                    'proveedor_codigo' => $row['proveedor_codigo'] ?? null,
                    'proveedor_nombre' => $row['proveedor_nombre'] ?? null,
                    'proveedor_ruc' => $row['proveedor_ruc'] ?? null,
                    'total' => 0,
                    'facturas_count' => 0,
                    'empresas' => [],
                ];
            }

            if (! isset($agrupado[$proveedorKey]['empresas'][$empresaKey])) {
                $agrupado[$proveedorKey]['empresas'][$empresaKey] = [
                    'conexion_id' => $row['conexion_id'] ?? null,
                    'conexion_nombre' => $row['conexion_nombre'] ?? null,
                    'empresa_codigo' => $row['empresa_codigo'] ?? null,
                    'empresa_nombre' => $row['empresa_nombre'] ?? null,
                    'sucursales' => [],
                ];
            }

            if (! isset($agrupado[$proveedorKey]['empresas'][$empresaKey]['sucursales'][$sucursalKey])) {
                $agrupado[$proveedorKey]['empresas'][$empresaKey]['sucursales'][$sucursalKey] = [
                    'sucursal_codigo' => $row['sucursal_codigo'] ?? null,
                    'sucursal_nombre' => $row['sucursal_nombre'] ?? null,
                    'facturas' => [],
                ];
            }

            $facturaKey = $this->buildFacturaKey($row['conexion_id'] ?? null, $row['empresa_codigo'] ?? null, $row['sucursal_codigo'] ?? null, $row['proveedor_codigo'] ?? null, $row['numero'] ?? null);

            $agrupado[$proveedorKey]['empresas'][$empresaKey]['sucursales'][$sucursalKey]['facturas'][] = [
                'key' => $facturaKey,
                'numero' => $row['numero'] ?? '',
                'fecha_emision' => $row['fecha_emision'] ?? null,
                'fecha_vencimiento' => $row['fecha_vencimiento'] ?? null,
                'saldo' => (float) ($row['saldo'] ?? 0),
                'empresa_codigo' => $row['empresa_codigo'] ?? null,
                'empresa_nombre' => $row['empresa_nombre'] ?? null,
                'sucursal_codigo' => $row['sucursal_codigo'] ?? null,
                'sucursal_nombre' => $row['sucursal_nombre'] ?? null,
                'conexion_id' => $row['conexion_id'] ?? null,
                'conexion_nombre' => $row['conexion_nombre'] ?? null,
            ];

            $agrupado[$proveedorKey]['total'] += (float) ($row['saldo'] ?? 0);
            $agrupado[$proveedorKey]['facturas_count']++;
        }

        foreach ($agrupado as &$proveedor) {
            foreach ($proveedor['empresas'] as &$empresa) {
                foreach ($empresa['sucursales'] as &$sucursal) {
                    $sucursal['facturas'] = collect($sucursal['facturas'])
                        ->sortBy('fecha_emision')
                        ->values()
                        ->all();
                }
                unset($sucursal);
                $empresa['sucursales'] = array_values($empresa['sucursales']);
            }
            unset($empresa);
            $proveedor['empresas'] = array_values($proveedor['empresas']);
        }
        unset($proveedor);

        $proveedores = collect($agrupado)
            ->sortBy('proveedor_nombre')
            ->values();

        return $proveedores
            ->values()
            ->all();
    }

    protected function applySearch($proveedores)
    {
        $termino = trim((string) ($this->search !== '' ? $this->search : ($this->filters['search'] ?? '')));

        if ($termino === '') {
            return $proveedores;
        }

        $termino = mb_strtolower($termino);

        return collect($proveedores)->filter(function (array $proveedor) use ($termino) {
            $matchesProveedor = str_contains(mb_strtolower($proveedor['proveedor_nombre'] ?? ''), $termino)
                || str_contains(mb_strtolower($proveedor['proveedor_codigo'] ?? ''), $termino)
                || str_contains(mb_strtolower($proveedor['proveedor_ruc'] ?? ''), $termino);

            if ($matchesProveedor) {
                return true;
            }

            foreach ($proveedor['empresas'] ?? [] as $empresa) {
                foreach ($empresa['sucursales'] ?? [] as $sucursal) {
                    foreach ($sucursal['facturas'] ?? [] as $factura) {
                        if (str_contains(mb_strtolower((string) ($factura['numero'] ?? '')), $termino)) {
                            return true;
                        }
                    }
                }
            }

            return false;
        });
    }

    public function getProvidersPaginatedProperty(): LengthAwarePaginator
    {
        $filtrados = $this->applySearch($this->facturasDisponibles);
        $proveedores = $this->applySort(collect($filtrados))->values();
        $page = $this->getPage();
        $items = $proveedores->forPage($page, $this->perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $proveedores->count(),
            $this->perPage,
            $page
        );
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    protected function applySort($proveedores)
    {
        if (! $this->sortField) {
            return collect($proveedores);
        }

        return collect($proveedores)->sortBy(
            function (array $proveedor) {
                return match ($this->sortField) {
                    'total' => (float) ($proveedor['total'] ?? 0),
                    'selected' => $this->providerHasSelection($proveedor) ? 1 : 0,
                    default => mb_strtolower($proveedor['proveedor_nombre'] ?? $proveedor['proveedor_codigo'] ?? ''),
                };
            },
            descending: $this->sortDirection === 'desc'
        );
    }

    protected function providerHasSelection(array $proveedor): bool
    {
        $selected = collect($this->selectedInvoices);

        foreach ($proveedor['empresas'] ?? [] as $empresa) {
            foreach ($empresa['sucursales'] ?? [] as $sucursal) {
                foreach ($sucursal['facturas'] ?? [] as $factura) {
                    if ($selected->contains($factura['key'] ?? null)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function buildFacturasDesdeSolicitud(SolicitudPago $solicitud): array
    {
        // nombre de la conexión (Empresa local)
        $conexionNombre = \App\Models\Empresa::query()
            ->where('id', $solicitud->id_empresa)
            ->value('nombre_empresa') ?? '';

        // catálogos externos de esa conexión
        $empresasOptions = SolicitudPagoResource::getEmpresasOptions($solicitud->id_empresa);

        // OJO: getSucursalesOptions necesita lista de empresas
        $empresasCodigos = collect($solicitud->detalles)->pluck('amdg_id_empresa')->filter()->unique()->values()->all();
        $sucursalesOptions = SolicitudPagoResource::getSucursalesOptions($solicitud->id_empresa, $empresasCodigos);

        $registros = collect();

        foreach ($solicitud->detalles as $detalle) {
            $empresaCodigo  = (string) ($detalle->amdg_id_empresa ?? '');
            $sucursalCodigo = (string) ($detalle->amdg_id_sucursal ?? '');

            $registros->push([
                'conexion_id' => $detalle->id_empresa,
                'conexion_nombre' => $conexionNombre,

                'empresa_codigo' => $empresaCodigo,
                'empresa_nombre' => $empresasOptions[$empresaCodigo] ?? $empresaCodigo,

                'sucursal_codigo' => $sucursalCodigo,
                'sucursal_nombre' => $sucursalesOptions[$sucursalCodigo] ?? $sucursalCodigo,

                'proveedor_codigo' => $detalle->proveedor_codigo ?? '',
                'proveedor_nombre' => $detalle->proveedor_nombre ?? ($detalle->proveedor_codigo ?? ''),
                'proveedor_ruc' => $detalle->proveedor_ruc,

                'numero' => $detalle->numero_factura ?? '',
                'fecha_emision' => $detalle->fecha_emision,
                'fecha_vencimiento' => $detalle->fecha_vencimiento,
                'total' => (float) ($detalle->total ?? $detalle->monto ?? $detalle->saldo ?? 0),
                'saldo' => (float) ($detalle->saldo ?? 0),
                'abono' => (float) ($detalle->abono ?? $detalle->saldo ?? 0),
                'estado_abono' => $detalle->estado_abono ?? $this->resolveEstadoAbono((float) ($detalle->total ?? $detalle->monto ?? $detalle->saldo ?? 0), (float) ($detalle->abono ?? $detalle->saldo ?? 0)),
            ]);
        }

        return $this->groupByProveedor($registros);
    }


    protected function buildFacturaKey(?string $conexion, ?string $empresa, ?string $sucursal, ?string $proveedor, ?string $numero): string
    {
        return trim(($conexion ?? '') . '|' . ($empresa ?? '') . '|' . ($sucursal ?? '') . '|' . ($proveedor ?? '') . '|' . ($numero ?? ''));
    }

    protected function buildProveedorKey(?string $codigo, ?string $ruc, ?string $nombre): string
    {
        return md5(trim(($codigo ?? '') . '|' . ($ruc ?? '') . '|' . ($nombre ?? '')));
    }

    protected function getEmpresasOptionsByConnections(array $conexiones): array
    {
        return collect($conexiones)
            ->flatMap(function ($conexion) {
                return collect(SolicitudPagoResource::getEmpresasOptions($conexion))
                    ->mapWithKeys(fn($nombre, $codigo) => [
                        $conexion . '|' . $codigo => $nombre,
                    ]);
            })
            ->all();
    }

    protected function getSucursalesOptionsByConnections(array $conexiones, array $empresasSeleccionadas): array
    {
        return collect($conexiones)
            ->flatMap(function ($conexion) use ($empresasSeleccionadas) {
                $empresas = $empresasSeleccionadas[$conexion] ?? [];

                return collect(SolicitudPagoResource::getSucursalesOptions($conexion, $empresas))
                    ->mapWithKeys(fn($nombre, $codigo) => [
                        $conexion . '|' . $codigo => $nombre,
                    ]);
            })
            ->all();
    }

    protected function groupOptionsByConnection(array $optionKeys): array
    {
        $agrupado = [];

        foreach ($optionKeys as $value) {
            [$conexion, $codigo] = array_pad(explode('|', (string) $value, 2), 2, null);

            if ($conexion && $codigo) {
                $agrupado[(int) $conexion][] = $codigo;
            }
        }

        return $agrupado;
    }

    protected function buildDefaultEmpresasSelection(array $conexiones): array
    {
        return collect($conexiones)
            ->flatMap(fn($conexion) => collect(SolicitudPagoResource::getEmpresasOptions($conexion))->keys()->map(fn($codigo) => $conexion . '|' . $codigo))
            ->values()
            ->all();
    }

    protected function buildDefaultSucursalesSelection(array $conexiones, array $empresasSeleccionadas): array
    {
        $empresas = $this->groupOptionsByConnection($empresasSeleccionadas);

        return collect($conexiones)
            ->flatMap(fn($conexion) => collect(SolicitudPagoResource::getSucursalesOptions($conexion, $empresas[$conexion] ?? []))
                ->keys()
                ->map(fn($codigo) => $conexion . '|' . $codigo))
            ->values()
            ->all();
    }

}
