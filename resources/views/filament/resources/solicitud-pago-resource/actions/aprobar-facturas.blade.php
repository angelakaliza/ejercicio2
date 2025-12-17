<div class="space-y-3">
    @php
        $facturasFiltradasIds = array_column($facturas, 'id');
        $seleccionActual = $seleccionadas ?? [];
        $todasSeleccionadas = array_values(array_unique(array_merge($seleccionActual, $facturasFiltradasIds)));
    @endphp

    <div class="flex flex-col gap-2 text-sm text-gray-700 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::button
                color="gray"
                size="sm"
                type="button"
                wire:click="$set('data.facturas_seleccionadas', @js($todasSeleccionadas))"
            >
                Seleccionar todas
            </x-filament::button>

            <x-filament::button
                color="primary"
                size="sm"
                type="button"
                wire:click="$set('data.facturas_seleccionadas', @js($seleccionAutomatica))"
            >
                Seleccionar automáticamente según monto
            </x-filament::button>
        </div>

        <div class="font-semibold">Facturas disponibles</div>
    </div>

    <div class="overflow-x-auto border rounded-lg">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="px-3 py-2 w-12"></th>
                    <th class="px-3 py-2">Factura</th>
                    <th class="px-3 py-2">Proveedor</th>
                    <th class="px-3 py-2">Empresa / Sucursal</th>
                    <th class="px-3 py-2">Emisión</th>
                    <th class="px-3 py-2">Vencimiento</th>
                    <th class="px-3 py-2 text-right">Saldo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($facturas as $factura)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2">
                            <input
                                type="checkbox"
                                class="rounded border-gray-300"
                                value="{{ $factura['id'] }}"
                                wire:model.live="data.facturas_seleccionadas"
                                wire:key="factura-{{ $factura['id'] }}"
                            >
                        </td>
                        <td class="px-3 py-2 font-medium text-gray-800">{{ $factura['numero'] }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $factura['proveedor'] }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $factura['empresa'] }} / {{ $factura['sucursal'] }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $factura['fecha_emision'] }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $factura['fecha_vencimiento'] }}</td>
                        <td class="px-3 py-2 text-right font-semibold text-gray-900">{{ number_format($factura['saldo'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-4 text-center text-gray-500">No hay facturas disponibles para esta solicitud.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
