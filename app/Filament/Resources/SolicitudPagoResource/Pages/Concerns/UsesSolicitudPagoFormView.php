<?php

namespace App\Filament\Resources\SolicitudPagoResource\Pages\Concerns;

use Filament\Resources\Pages\ViewRecord;

trait UsesSolicitudPagoFormView
{
    public function getFacturasDisponiblesProperty(): array
    {
        return $this->data['facturas_disponibles'] ?? [];
    }

    public function getTotalSeleccionadoProperty(): float
    {
        return collect($this->facturasDisponibles)
            ->flatMap(fn(array $empresa) => collect($empresa['sucursales'] ?? []))
            ->flatMap(fn(array $sucursal) => collect($sucursal['proveedores'] ?? []))
            ->flatMap(fn(array $proveedor) => collect($proveedor['facturas'] ?? []))
            ->filter(fn(array $factura) => $factura['seleccionado'] ?? false)
            ->sum(fn(array $factura) => (float) ($factura['saldo'] ?? 0));
    }

    public function getReadOnlyViewProperty(): bool
    {
        return $this instanceof ViewRecord;
    }
}
