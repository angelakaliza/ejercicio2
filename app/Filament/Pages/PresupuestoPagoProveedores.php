<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SolicitudPagoResource;
use App\Models\Empresa;
use App\Models\SolicitudPago;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Collection;
use Livewire\WithPagination;

class PresupuestoPagoProveedores extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static string $view = 'filament.pages.presupuesto-pago-proveedores';

    protected static ?string $navigationGroup = 'Solicitudes de Pago y Aprobaciones';

    protected static ?string $title = 'Presupuesto de pago a proveedores';

    protected static ?string $navigationLabel = 'Presupuesto de pago a proveedores';

    public ?array $filters = [];

    public array $facturasDisponibles = [];

    public array $providerTotals = [];

    public array $selectedProviders = [];

    public array $providerDescriptions = [];

    public array $providerAreas = [];

    public array $openProviders = [];

    public int $perPage = 10;
    public string $search = '';
    public ?string $sortField = 'proveedor_nombre';
    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $this->form->fill([
            'fecha_desde' => Carbon::now()->subYears(5)->startOfDay(),
            'fecha_hasta' => Carbon::now()->endOfDay(),
            'conexiones' => [],
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }


    public function form(Form $form): Form
    {
        return $form
            ->statePath('filters')
            ->schema([
                Section::make('Filtros')
                    ->columns(4)
                    ->schema([
                        Select::make('conexiones')
                            ->label('Conexiones')
                            ->multiple()
                            ->options(Empresa::query()->pluck('nombre_empresa', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, ?array $state): void {
                                $empresas = $this->buildDefaultEmpresasSelection($state ?? []);
                                $sucursales = $this->buildDefaultSucursalesSelection($state ?? [], $empresas);

                                $set('empresas', $empresas);
                                $set('sucursales', $sucursales);
                                $this->resetPage();
                                $this->resetPresupuestoData();
                            }),
                        Select::make('empresas')
                            ->label('Empresas')
                            ->multiple()
                            ->options(fn(Forms\Get $get): array => $this->getEmpresasOptionsByConnections($get('conexiones') ?? []))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (): void {
                                $this->syncSucursales();
                                $this->resetPage();
                                $this->resetPresupuestoData();
                            }),
                        Select::make('sucursales')
                            ->label('Sucursales')
                            ->multiple()
                            ->options(fn(Forms\Get $get): array => $this->getSucursalesOptionsByConnections(
                                $get('conexiones') ?? [],
                                $this->groupOptionsByConnection($get('empresas') ?? []),
                            ))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (): void {
                                $this->resetPage();
                                $this->resetPresupuestoData();
                            }),
                        DatePicker::make('fecha_desde')
                            ->label('Fecha desde')
                            ->default(Carbon::now()->subYears(5)->startOfDay())
                            ->live()
                            ->afterStateUpdated(fn() => $this->resetPresupuestoData()),
                        DatePicker::make('fecha_hasta')
                            ->label('Fecha hasta')
                            ->default(Carbon::now()->endOfDay())
                            ->live()
                            ->afterStateUpdated(fn() => $this->resetPresupuestoData()),
                        Actions::make([
                            FormAction::make('generateReport')
                                ->label('Generar reporte')
                                ->color('primary')
                                ->icon('heroicon-o-document-arrow-down')
                                ->action(fn() => $this->generateReport()),
                        ])->columnSpan(1),
                    ]),
            ]);
    }

    public function generateReport(): void
    {
        $this->resetPage();
        $this->loadPresupuesto();
    }

    protected function resetPresupuestoData(): void
    {
        $this->selectedProviders = [];
        $this->providerTotals = [];
        $this->facturasDisponibles = [];
        $this->providerDescriptions = [];
        $this->providerAreas = [];
        $this->openProviders = [];
    }

    protected function syncSucursales(): void
    {
        $conexiones = $this->filters['conexiones'] ?? [];
        $empresas = $this->filters['empresas'] ?? [];

        $this->filters['sucursales'] = $this->buildDefaultSucursalesSelection($conexiones, $empresas);
    }

    public function loadPresupuesto(): void
    {
        $conexiones = $this->filters['conexiones'] ?? [];
        $empresasSeleccionadas = $this->groupOptionsByConnection($this->filters['empresas'] ?? []);
        $sucursalesSeleccionadas = $this->groupOptionsByConnection($this->filters['sucursales'] ?? []);
        $desde = $this->filters['fecha_desde'] ?? null;
        $hasta = $this->filters['fecha_hasta'] ?? null;

        $this->resetPresupuestoData();

        if (empty($conexiones)) {
            return;
        }

        $this->facturasDisponibles = $this->buildPresupuesto(
            $conexiones,
            $empresasSeleccionadas,
            $sucursalesSeleccionadas,
            $desde,
            $hasta,
        );
        $this->providerTotals = $this->collectProviderTotals($this->facturasDisponibles);
        $this->syncProviderMetadata($this->facturasDisponibles);
    }

    protected function collectProviderTotals(array $proveedores): array
    {
        return collect($proveedores)
            ->mapWithKeys(fn(array $proveedor) => [
                $proveedor['key'] => (float) ($proveedor['total'] ?? 0),
            ])
            ->all();
    }

    protected function syncProviderMetadata(array $proveedores): void
    {
        $existingAreas = $this->providerAreas;
        $existingDescriptions = $this->providerDescriptions;
        $existingOpen = $this->openProviders;

        $this->providerAreas = [];
        $this->providerDescriptions = [];
        $this->openProviders = [];

        foreach ($proveedores as $proveedor) {
            $key = $proveedor['key'];

            $this->providerAreas[$key] = $existingAreas[$key] ?? $this->computeAreaForProveedor($proveedor);
            $this->providerDescriptions[$key] = $existingDescriptions[$key] ?? '';

            if (in_array($key, $existingOpen, true)) {
                $this->openProviders[] = $key;
            }
        }
    }

    protected function computeAreaForProveedor(array $proveedor): string
    {
        $sucursales = collect($proveedor['empresas'] ?? [])
            ->flatMap(fn(array $empresa) => $empresa['sucursales'] ?? [])
            ->pluck('sucursal_nombre')
            ->filter()
            ->map(fn(string $nombre) => mb_strtolower(trim($nombre)))
            ->unique();

        if ($sucursales->isEmpty()) {
            return 'P';
        }

        $soloMatriz = $sucursales->every(fn(string $nombre) => $nombre === 'matriz');

        return $soloMatriz ? 'M' : 'P';
    }

    protected function buildPresupuesto(array $conexiones, array $empresasSeleccionadas, array $sucursalesSeleccionadas, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $connectionNames = Empresa::query()->pluck('nombre_empresa', 'id');

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
            ->when($fechaDesde, fn($q) => $q->whereDate('saedmcp.dcmp_fec_emis', '>=', $fechaDesde))
            ->when($fechaHasta, fn($q) => $q->whereDate('saedmcp.dcmp_fec_emis', '<=', $fechaHasta))
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
            ->orderBy('prov.clpv_nom_clpv')
            ->havingRaw('SUM(COALESCE(saedmcp.dcmp_deb_ml,0) - COALESCE(saedmcp.dcmp_cre_ml,0)) <> 0');

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
                    'numero_factura' => $row->numero_factura,
                    'fecha_emision' => $row->fecha_emision,
                    'fecha_vencimiento' => $row->fecha_vencimiento,
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
                    'proveedor_codigos' => [],
                    'proveedor_nombre' => $row['proveedor_nombre'] ?? null,
                    'proveedor_ruc' => $row['proveedor_ruc'] ?? null,
                    'total' => 0,
                    'facturas_count' => 0,
                    'empresas' => [],
                ];
            }

            $codigo = (string) ($row['proveedor_codigo'] ?? '');
            if ($codigo !== '' && ! in_array($codigo, $agrupado[$proveedorKey]['proveedor_codigos'], true)) {
                $agrupado[$proveedorKey]['proveedor_codigos'][] = $codigo;
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

            $agrupado[$proveedorKey]['empresas'][$empresaKey]['sucursales'][$sucursalKey]['facturas'][] = [
                'numero' => $row['numero_factura'] ?? '',
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

        return $proveedores->values()->all();
    }

    protected function applySearch($proveedores)
    {
        $termino = trim($this->search ?? '');

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


    protected function buildProveedorKey(?string $codigo, ?string $ruc, ?string $nombre): string
    {
        $ruc = preg_replace('/\s+/', '', (string) $ruc);
        $ruc = preg_replace('/[^0-9A-Za-z]/', '', $ruc); // limpia guiones/puntos

        if (! empty($ruc)) {
            // ✅ si hay RUC, es la llave
            return 'ruc:' . mb_strtolower($ruc);
        }

        // fallback: nombre normalizado
        $nombre = mb_strtolower(trim((string) $nombre));
        $nombre = preg_replace('/\s+/', ' ', $nombre);

        if ($nombre !== '') {
            return 'nom:' . md5($nombre);
        }

        // último fallback
        return 'cod:' . mb_strtolower(trim((string) $codigo));
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

    public function getTotalSeleccionadoProperty(): float
    {
        return collect($this->selectedProviders)
            ->sum(fn(string $key) => $this->providerTotals[$key] ?? 0);
    }

    public function getProvidersPaginatedProperty(): LengthAwarePaginator
    {
        $proveedores = collect($this->facturasDisponibles);

        // ✅ filtra aquí (en vivo) usando la barra de abajo
        $proveedores = $this->applySearch($proveedores);
        $proveedores = $this->applySort($proveedores)->values();

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

    protected function applySort(Collection $proveedores): Collection
    {
        if (! $this->sortField) {
            return $proveedores;
        }

        return $proveedores->sortBy(
            function (array $proveedor) {
                return match ($this->sortField) {
                    'total' => (float) ($proveedor['total'] ?? 0),
                    'selected' => in_array($proveedor['key'] ?? '', $this->selectedProviders, true) ? 1 : 0,
                    default => mb_strtolower($proveedor['proveedor_nombre'] ?? implode(', ', $proveedor['proveedor_codigos'] ?? [])),
                };
            },
            descending: $this->sortDirection === 'desc'
        );
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
                        ->content(fn() => '$' . number_format($this->totalSeleccionado, 2, '.', ',')),

                    Forms\Components\TextInput::make('monto_aprobado')
                        ->label('Monto aprobado')
                        ->required()
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0.01)
                        ->rule('gt:0'),

                    Forms\Components\Textarea::make('motivo')
                        ->label('Comentario / Motivo')
                        ->required()
                        ->rows(4)
                        ->maxLength(1000)
                        ->placeholder('Ingrese el motivo o comentario de la solicitud de pago'),
                ])
                ->action(fn(array $data) => $this->createSolicitudPago($data)),
            Action::make('exportPdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('danger')
                ->form([
                    Forms\Components\TextInput::make('descripcion_reporte')
                        ->label('Descripción del reporte')
                        ->placeholder('Presupuesto del 15 de enero al 18 de mayo')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(fn(array $data) => $this->exportPdf($data['descripcion_reporte'] ?? '')),
            Action::make('exportExcel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('descripcion_reporte')
                        ->label('Descripción del reporte')
                        ->placeholder('Presupuesto del 15 de enero al 18 de mayo')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(fn(array $data) => $this->exportExcel($data['descripcion_reporte'] ?? '')),
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

        $conexion = collect($this->filters['conexiones'] ?? [])->first();

        if (! $conexion) {
            Notification::make()
                ->title('Seleccione una conexión para crear la solicitud')
                ->warning()
                ->send();

            return;
        }

        $montoEstimado = $this->totalSeleccionado;
        $montoAprobado = (float) ($data['monto_aprobado'] ?? 0);

        if ($montoAprobado <= 0) {
            Notification::make()
                ->title('Ingrese un monto aprobado válido')
                ->warning()
                ->send();

            return;
        }

        $empresasSeleccionadas = $this->groupOptionsByConnection($this->filters['empresas'] ?? []);
        $sucursalesSeleccionadas = $this->groupOptionsByConnection($this->filters['sucursales'] ?? []);
        $primerProveedor = collect($selected)
            ->flatMap(fn($p) => $p['proveedor_codigos'] ?? [])
            ->filter()
            ->first();
        $proveedorNombre = collect($selected)->pluck('proveedor_nombre')->filter()->unique()->implode(', ');

        DB::transaction(function () use ($conexion, $empresasSeleccionadas, $sucursalesSeleccionadas, $selected, $montoEstimado, $montoAprobado, $primerProveedor, $proveedorNombre) {
            $solicitud = SolicitudPago::create([
                'id_empresa' => $conexion,
                'amdg_id_empresa' => collect($empresasSeleccionadas)->flatten()->first() ?? '',
                'amdg_id_sucursal' => collect($sucursalesSeleccionadas)->flatten()->first() ?? null,
                'proveedor_id' => $primerProveedor ?? '',
                'proveedor_nombre' => $proveedorNombre,
                'fecha' => Carbon::now(),
                'tipo_solicitud' => 'Presupuesto de Pago a Proveedores',
                'empresas_seleccionadas' => $empresasSeleccionadas,
                'sucursales_seleccionadas' => $sucursalesSeleccionadas,
                'proveedores_seleccionados' => $this->selectedProviders,
                'total' => $montoEstimado,
                'monto_estimado' => $montoEstimado,
                'monto_aprobado' => $montoAprobado,
                'motivo' => $data['motivo'] ?? null, // 👈 NUEVO
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
            ->flatMap(fn(array $proveedor) => collect($proveedor['empresas'] ?? [])->flatMap(function (array $empresa) use ($conexion, $proveedor) {
                return collect($empresa['sucursales'] ?? [])->flatMap(function (array $sucursal) use ($conexion, $proveedor, $empresa) {
                    return collect($sucursal['facturas'] ?? [])->map(function (array $factura) use ($conexion, $proveedor, $empresa, $sucursal) {
                        return [
                            'id_empresa' => $factura['conexion_id'] ?? $conexion,
                            'amdg_id_empresa' => $empresa['empresa_codigo'] ?? '',
                            'amdg_id_sucursal' => $sucursal['sucursal_codigo'] ?? null,
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
                });
            }))
            ->values()
            ->all();
    }

    protected function getSelectedProviders(): array
    {
        return collect($this->facturasDisponibles)
            ->filter(fn(array $proveedor) => in_array($proveedor['key'], $this->selectedProviders, true))
            ->map(function (array $proveedor) {
                $key = $proveedor['key'];

                $proveedor['area'] = $this->providerAreas[$key] ?? $this->computeAreaForProveedor($proveedor);
                $proveedor['descripcion'] = $this->providerDescriptions[$key] ?? '';

                return $proveedor;
            })
            ->values()
            ->all();
    }

    public function setOpenProvider(string $key, bool $isOpen): void
    {
        if ($isOpen) {
            if (! in_array($key, $this->openProviders, true)) {
                $this->openProviders[] = $key;
            }

            return;
        }

        $this->openProviders = array_values(array_filter(
            $this->openProviders,
            fn(string $value) => $value !== $key,
        ));
    }

    protected function groupByEmpresaForReport(array $selected): array
    {
        $empresas = [];

        foreach ($selected as $proveedor) {
            foreach ($proveedor['empresas'] ?? [] as $empresa) {
                $empresaKey = ($empresa['conexion_id'] ?? '') . '|' . ($empresa['empresa_codigo'] ?? '');

                if (! isset($empresas[$empresaKey])) {
                    $empresas[$empresaKey] = [
                        'conexion_nombre' => $empresa['conexion_nombre'] ?? '',
                        'empresa_codigo' => $empresa['empresa_codigo'] ?? '',
                        'empresa_nombre' => $empresa['empresa_nombre'] ?? ($empresa['empresa_codigo'] ?? ''),
                        'proveedores' => [],
                        'subtotal' => 0,
                    ];
                }

                $proveedorData = [
                    'nombre' => $proveedor['proveedor_nombre'] ?? $proveedor['proveedor_codigo'],
                    'ruc' => $proveedor['proveedor_ruc'] ?? '',
                    'area' => $proveedor['area'] ?? $this->computeAreaForProveedor($proveedor),
                    'descripcion' => $proveedor['descripcion'] ?? '',
                    'facturas' => [],
                    'subtotal' => 0,
                ];

                foreach ($empresa['sucursales'] ?? [] as $sucursal) {
                    foreach ($sucursal['facturas'] ?? [] as $factura) {
                        $monto = (float) ($factura['saldo'] ?? 0);

                        $proveedorData['facturas'][] = [
                            'sucursal' => $sucursal['sucursal_nombre'] ?? ($sucursal['sucursal_codigo'] ?? ''),
                            'numero' => $factura['numero'] ?? '',
                            'fecha_emision' => $factura['fecha_emision'] ?? '',
                            'fecha_vencimiento' => $factura['fecha_vencimiento'] ?? '',
                            'saldo' => $monto,
                        ];

                        $proveedorData['subtotal'] += $monto;
                    }
                }

                if (! empty($proveedorData['facturas'])) {
                    $empresas[$empresaKey]['proveedores'][] = $proveedorData;
                    $empresas[$empresaKey]['subtotal'] += $proveedorData['subtotal'];
                }
            }
        }

        return array_values($empresas);
    }

    protected function buildResumenPorEmpresa(array $empresas): array
    {
        return collect($empresas)
            ->map(fn(array $empresa) => [
                'empresa' => $empresa['empresa_nombre'] ?? $empresa['empresa_codigo'],
                'total' => (float) ($empresa['subtotal'] ?? 0),
            ])
            ->all();
    }

    protected function exportPdf(string $descripcionReporte)
    {
        $selected = $this->ensureSelection();

        if ($selected === null) {
            return null;
        }

        $empresas = $this->groupByEmpresaForReport($selected);

        return response()->streamDownload(function () use ($descripcionReporte, $empresas) {
            echo Pdf::loadView('pdfs.presupuesto-pago-proveedores', [
                'empresas' => $empresas,
                'total' => $this->totalSeleccionado,
                'resumenEmpresas' => $this->buildResumenPorEmpresa($empresas),
                'descripcionReporte' => $descripcionReporte,
                'usuario' => Auth::user()?->name,
            ])->setPaper('a4', 'landscape')->stream();
        }, 'presupuesto-pago-proveedores.pdf');
    }

    protected function exportExcel(string $descripcionReporte)
    {
        $selected = $this->ensureSelection();

        if ($selected === null) {
            return null;
        }

        $rows = [];

        foreach ($selected as $proveedor) {
            foreach ($proveedor['empresas'] ?? [] as $empresa) {
                foreach ($empresa['sucursales'] ?? [] as $sucursal) {
                    foreach ($sucursal['facturas'] ?? [] as $factura) {
                        $rows[] = [
                            'Conexion' => $factura['conexion_nombre'] ?? '',
                            'Empresa' => $empresa['empresa_nombre'] ?? $empresa['empresa_codigo'],
                            'Sucursal' => $sucursal['sucursal_nombre'] ?? $sucursal['sucursal_codigo'],
                            'Proveedor' => $proveedor['proveedor_nombre'] ?? $proveedor['proveedor_codigo'],
                            'RUC' => $proveedor['proveedor_ruc'] ?? '',
                            'Descripcion' => $proveedor['descripcion'] ?? '',
                            'Area' => $proveedor['area'] ?? $this->computeAreaForProveedor($proveedor),
                            'Factura' => $factura['numero'] ?? '',
                            'Fecha Emision' => $factura['fecha_emision'] ?? '',
                            'Fecha Vencimiento' => $factura['fecha_vencimiento'] ?? '',
                            'Saldo Factura' => number_format((float) ($factura['saldo'] ?? 0), 2, '.', ''),
                            'Total Proveedor' => number_format((float) ($proveedor['total'] ?? 0), 2, '.', ''),
                            'Conexion Empresa' => $empresa['conexion_nombre'] ?? '',
                        ];
                    }
                }
            }
        }

        return response()->streamDownload(function () use ($rows, $descripcionReporte) {
            $handle = fopen('php://output', 'wb');

            if (! empty($descripcionReporte)) {
                fputcsv($handle, [$descripcionReporte]);
                fputcsv($handle, []);
            }

            fputcsv($handle, array_keys($rows[0] ?? [
                'Conexion' => 'Conexion',
                'Empresa' => 'Empresa',
                'Sucursal' => 'Sucursal',
                'Proveedor' => 'Proveedor',
                'RUC' => 'RUC',
                'Descripcion' => 'Descripcion',
                'Area' => 'Area',
                'Factura' => 'Factura',
                'Fecha Emision' => 'Fecha Emision',
                'Fecha Vencimiento' => 'Fecha Vencimiento',
                'Saldo Factura' => 'Saldo Factura',
                'Total Proveedor' => 'Total Proveedor',
                'Conexion Empresa' => 'Conexion Empresa',
            ]));

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'presupuesto-pago-proveedores.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function buildPeriodoLabel(): string
    {
        $desde = $this->filters['fecha_desde'] ?? null;
        $hasta = $this->filters['fecha_hasta'] ?? null;

        if ($desde && $hasta) {
            return Carbon::parse($desde)->translatedFormat('j \de F Y') . ' al ' . Carbon::parse($hasta)->translatedFormat('j \de F Y');
        }

        return '1 DE ENERO 2024 AL 31 DE FEBRERO 2025';
    }
}
