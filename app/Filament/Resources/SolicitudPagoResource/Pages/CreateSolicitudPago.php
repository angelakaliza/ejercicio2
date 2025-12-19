<?php

namespace App\Filament\Resources\SolicitudPagoResource\Pages;

use App\Filament\Resources\SolicitudPagoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSolicitudPago extends CreateRecord
{
    protected static string $resource = SolicitudPagoResource::class;

    protected static string $view = 'filament.resources.solicitud-pago-resource.pages.formulario-solicitud';

    protected array $facturasSeleccionadas = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        [$data, $facturas] = $this->prepareFacturas($data);
        $this->facturasSeleccionadas = $facturas;

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! empty($this->facturasSeleccionadas)) {
            $this->record->detalles()->createMany($this->facturasSeleccionadas);
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    protected function prepareFacturas(array $data): array
    {
        // Esta es la empresa de la solicitud (conexión)
        $idEmpresaSolicitud = $data['id_empresa'] ?? null;

        $empresas = collect($data['facturas_disponibles'] ?? []);

        $facturas = $empresas
            ->flatMap(function ($empresa) {
                return collect($empresa['sucursales'] ?? [])->flatMap(function ($sucursal) use ($empresa) {
                    return collect($sucursal['proveedores'] ?? [])->flatMap(function ($proveedor) use ($empresa, $sucursal) {
                        return collect($proveedor['facturas'] ?? [])->filter(fn($factura) => $factura['seleccionado'] ?? false)
                            ->map(function ($factura) use ($empresa, $sucursal, $proveedor) {
                                return [
                                    // estos 3 vienen de la estructura anidada
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

        // Aquí sí usamos la empresa de la solicitud
        $facturas = collect($facturas)
            ->map(function ($f) use ($idEmpresaSolicitud) {
                $f['id_empresa'] = $idEmpresaSolicitud;  // 👈 YA NO ES NULL
                return $f;
            })
            ->all();

        // Total de la solicitud
        $data['total'] = collect($facturas)->sum('saldo');

        // Quitamos el campo sólo de uso de formulario
        unset($data['facturas_disponibles']);

        // Valores "representativos" para la cabecera
        $data['amdg_id_empresa']   = $data['empresas_seleccionadas'][0]    ?? '';
        $data['amdg_id_sucursal']  = $data['sucursales_seleccionadas'][0]  ?? null;

        $data['proveedor_id'] = collect($data['proveedores_seleccionados'] ?? [])
            ->map(fn(string $valor) => SolicitudPagoResource::parseProveedorKey($valor)['proveedor'] ?? null)
            ->filter()
            ->first() ?? '';

        $proveedorOptions = SolicitudPagoResource::getProveedoresOptions(
            $idEmpresaSolicitud,
            $data['empresas_seleccionadas'] ?? [],
            $data['sucursales_seleccionadas'] ?? []
        );
        $proveedoresBase = SolicitudPagoResource::getProveedoresBase(
            $idEmpresaSolicitud,
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
