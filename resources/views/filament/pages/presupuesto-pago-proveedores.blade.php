<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">
                Tabla agrupada por Empresa → Sucursal → Proveedor
            </x-slot>

            <div class="text-sm text-gray-600">
                Selecciona los proveedores desde los encabezados para calcular el total consolidado y habilitar las exportaciones.
            </div>

            <div class="space-y-3 mt-4">
                @forelse ($tablaAgrupada as $empresa)
                    <details class="rounded border border-gray-200 bg-white shadow-sm" open>
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold flex items-center gap-2">
                            <span class="text-primary-600">Empresa:</span>
                            <span>{{ $empresa['nombre'] ?? $empresa['codigo'] }}</span>
                        </summary>

                        <div class="divide-y">
                            @foreach ($empresa['sucursales'] as $sucursal)
                                <details class="px-4 py-3" open>
                                    <summary class="cursor-pointer text-sm font-semibold flex items-center gap-2">
                                        <span class="text-primary-500">Sucursal:</span>
                                        <span>{{ $sucursal['nombre'] ?? $sucursal['codigo'] }}</span>
                                    </summary>

                                    <div class="mt-2 space-y-3">
                                        @foreach ($sucursal['proveedores'] as $proveedor)
                                            <details class="rounded border border-gray-100 bg-gray-50">
                                                <summary class="flex items-center gap-3 px-4 py-3 text-sm font-semibold">
                                                    <input
                                                        type="checkbox"
                                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                                        wire:model="proveedoresSeleccionados"
                                                        value="{{ $proveedor['key'] }}"
                                                    >
                                                    <span class="flex-1">
                                                        {{ $proveedor['nombre'] ?? $proveedor['codigo'] }}
                                                        @if(!empty($proveedor['ruc']))
                                                            <span class="text-gray-500 font-normal">({{ $proveedor['ruc'] }})</span>
                                                        @endif
                                                    </span>
                                                    <span class="text-xs uppercase tracking-wide text-gray-500">Total proveedor</span>
                                                    <span class="text-base font-bold">${{ number_format($proveedor['total'] ?? 0, 2, '.', ',') }}</span>
                                                </summary>

                                                <div class="px-4 pb-4">
                                                    <div class="overflow-x-auto">
                                                        <table class="min-w-full divide-y divide-gray-200">
                                                            <thead class="bg-gray-100">
                                                                <tr>
                                                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Factura</th>
                                                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Emisión</th>
                                                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Vencimiento</th>
                                                                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Saldo</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-gray-100">
                                                                @foreach ($proveedor['facturas'] as $factura)
                                                                    <tr>
                                                                        <td class="px-3 py-2 text-sm">{{ $factura['numero'] }}</td>
                                                                        <td class="px-3 py-2 text-sm">{{ $factura['fecha_emision'] }}</td>
                                                                        <td class="px-3 py-2 text-sm">{{ $factura['fecha_vencimiento'] }}</td>
                                                                        <td class="px-3 py-2 text-sm text-right font-semibold">
                                                                            ${{ number_format($factura['saldo'] ?? 0, 2, '.', ',') }}
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </details>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <div class="text-sm text-gray-500">Ajusta los filtros para visualizar el presupuesto de pago a proveedores.</div>
                @endforelse
            </div>

            <div class="mt-4 flex justify-end">
                <div class="rounded bg-amber-50 px-4 py-3 text-right text-base font-bold text-amber-700">
                    Total seleccionado: ${{ number_format($totalSeleccionado, 2, '.', ',') }}
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
