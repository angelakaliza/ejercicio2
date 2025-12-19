<?php

namespace App\Filament\Resources\SolicitudPagoResource\Pages;

use App\Filament\Resources\SolicitudPagoResource;
use App\Filament\Resources\SolicitudPagoResource\Pages\Concerns\UsesSolicitudPagoFormView;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Filament\Support\Enums\Operation;

class EditSolicitudPago extends EditRecord
{
    use UsesSolicitudPagoFormView;

    protected static string $resource = SolicitudPagoResource::class;

    protected static string $view = 'filament.resources.solicitud-pago-resource.pages.solicitud-pago-form';

    protected array $facturasSeleccionadas = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;

        $empresaId = $record->id_empresa;

        $data['empresas_seleccionadas'] = $record->empresas_seleccionadas ?? array_filter([$record->amdg_id_empresa]);
        $data['sucursales_seleccionadas'] = $record->sucursales_seleccionadas ?? array_filter([$record->amdg_id_sucursal]);
        $data['proveedores_seleccionados'] = $record->detalles
            ->map(fn($detalle) => ($detalle->amdg_id_sucursal ?? '') . '|' . $detalle->proveedor_codigo)
            ->unique()
            ->values()
            ->all();

        if (empty($data['proveedores_seleccionados'])) {
            $data['proveedores_seleccionados'] = $record->proveedores_seleccionados ?? array_filter([$record->proveedor_id]);
        }

        $clavesSeleccionadas = $record->detalles
            ->map(fn($detalle) => SolicitudPagoResource::getFacturaKey([
                'amdg_id_empresa'  => $detalle->amdg_id_empresa,
                'amdg_id_sucursal' => $detalle->amdg_id_sucursal,
                'proveedor_codigo' => $detalle->proveedor_codigo,
                'numero'           => $detalle->numero_factura,
            ]))
            ->values()
            ->all();

        $proveedoresSeleccionados = collect($data['proveedores_seleccionados'] ?? [])
            ->map(fn(string $valor) => SolicitudPagoResource::parseProveedorKey($valor))
            ->filter(fn($item) => filled($item['proveedor']))
            ->values()
            ->all();

        $data['facturas_disponibles'] = SolicitudPagoResource::buildFacturasDisponibles(
            $empresaId,
            $data['empresas_seleccionadas'],
            $data['sucursales_seleccionadas'],
            $proveedoresSeleccionados,
            $clavesSeleccionadas
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        [$data, $facturas] = $this->prepareFacturas($data);
        $this->facturasSeleccionadas = $facturas;

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->detalles()->delete();

        if (! empty($this->facturasSeleccionadas)) {
            $this->record->detalles()->createMany($this->facturasSeleccionadas);
        }
    }

    protected function getRedirectUrl(): string
    {
        // Volver a cargar la misma página de edición para refrescar la tabla de abajo
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    protected function prepareFacturas(array $data): array
    {
        $empresas = collect($data['facturas_disponibles'] ?? []);

        $facturas = $empresas
            ->flatMap(function ($empresa) {
                return collect($empresa['sucursales'] ?? [])->flatMap(function ($sucursal) use ($empresa) {
                    return collect($sucursal['proveedores'] ?? [])->flatMap(function ($proveedor) use ($empresa, $sucursal) {
                        return collect($proveedor['facturas'] ?? [])->filter(fn($factura) => $factura['seleccionado'] ?? false)
                            ->map(function ($factura) use ($empresa, $sucursal, $proveedor) {
                                return [
                                    'amdg_id_empresa'  => $factura['amdg_id_empresa']  ?? $empresa['empresa_codigo']   ?? '',
                                    'amdg_id_sucursal' => $factura['amdg_id_sucursal'] ?? $sucursal['sucursal_codigo'] ?? null,
                                    'proveedor_codigo' => $factura['proveedor_codigo'] ?? $proveedor['proveedor_codigo'] ?? '',
                                    'proveedor_nombre' => $factura['proveedor_nombre'] ?? $proveedor['proveedor_nombre'] ?? '',
                                    'numero_factura'   => $factura['numero'] ?? '',
                                    'fecha_emision'    => $factura['fecha_emision'] ?? null,
                                    'fecha_vencimiento' => $factura['fecha_vencimiento'] ?? null,
                                    'proveedor_ruc'    => $factura['proveedor_ruc'] ?? $proveedor['proveedor_ruc'] ?? null,
                                    'monto'            => isset($factura['monto']) ? (float) $factura['monto'] : (float) ($factura['saldo'] ?? 0),
                                    'saldo'            => (float) ($factura['saldo'] ?? 0),
                                ];
                            });
                    });
                });
            })
            ->values()
            ->all();

        $facturas = collect($facturas)
            ->map(function ($f) use ($data) {
                $f['id_empresa'] = $data['id_empresa'];
                return $f;
            })
            ->all();

        $data['total'] = collect($facturas)->sum('saldo');
        unset($data['facturas_disponibles']);

        $data['amdg_id_empresa'] = $data['empresas_seleccionadas'][0] ?? '';
        $data['amdg_id_sucursal'] = $data['sucursales_seleccionadas'][0] ?? null;
        $data['proveedor_id'] = collect($data['proveedores_seleccionados'] ?? [])
            ->map(fn(string $valor) => SolicitudPagoResource::parseProveedorKey($valor)['proveedor'] ?? null)
            ->filter()
            ->first() ?? '';

        $proveedorOptions = SolicitudPagoResource::getProveedoresOptions(
            $data['id_empresa'],
            $data['empresas_seleccionadas'] ?? [],
            $data['sucursales_seleccionadas'] ?? []
        );
        $proveedoresBase = SolicitudPagoResource::getProveedoresBase(
            $data['id_empresa'],
            $data['empresas_seleccionadas'] ?? [],
            $data['sucursales_seleccionadas'] ?? []
        );

        $data['proveedor_nombre'] = collect($data['proveedores_seleccionados'] ?? [])
            ->map(function (string $valor) use ($proveedoresBase, $proveedorOptions) {
                $base = $proveedoresBase[$valor] ?? null;

                return $base['nombre'] ?? ($proveedorOptions[$valor] ?? $valor);
            })
            ->implode(', ');

        return [$data, $facturas];
    }


}
