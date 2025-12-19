<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">
                Selección de proveedores
            </x-slot>

            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    Seleccione proveedores para consolidar el presupuesto.
                </div>
                <div class="text-lg font-semibold text-amber-600">
                    Total seleccionado: ${{ number_format($this->totalSeleccionado, 2, '.', ',') }}
                </div>
            </div>

            <div class="mt-4 space-y-4">
                @forelse ($this->facturasDisponibles as $empresa)
                    <x-filament::card>
                        <div class="text-base font-semibold text-gray-700">
                            {{ $empresa['empresa_nombre'] ?? $empresa['empresa_codigo'] }}
                        </div>

                        <div class="mt-3 space-y-3">
                            @foreach ($empresa['sucursales'] ?? [] as $sucursal)
                                <div class="overflow-hidden rounded-xl border border-gray-200">
                                    <div class="bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-700">
                                        {{ $sucursal['sucursal_nombre'] ?? $sucursal['sucursal_codigo'] }}
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Proveedor</th>
                                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Facturas</th>
                                                    <th class="px-4 py-2 text-right font-semibold text-gray-700">Total</th>
                                                    <th class="px-4 py-2 text-center font-semibold text-gray-700">Seleccionar</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 bg-white">
                                                @foreach ($sucursal['proveedores'] ?? [] as $proveedor)
                                                    <tr class="align-top">
                                                        <td class="px-4 py-3">
                                                            <div class="font-semibold text-gray-800">
                                                                {{ $proveedor['proveedor_nombre'] ?? $proveedor['proveedor_codigo'] }}
                                                            </div>
                                                            <div class="text-xs text-gray-500">
                                                                Código: {{ $proveedor['proveedor_codigo'] }}
                                                                @if (!empty($proveedor['proveedor_ruc']))
                                                                    · RUC: {{ $proveedor['proveedor_ruc'] }}
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <details class="rounded-md border border-gray-200 bg-slate-50 p-3">
                                                                <summary class="cursor-pointer text-sm font-semibold text-slate-700">
                                                                    {{ count($proveedor['facturas'] ?? []) }} factura(s)
                                                                </summary>
                                                                <div class="mt-2 overflow-x-auto">
                                                                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                                                                        <thead class="bg-white">
                                                                            <tr>
                                                                                <th class="px-3 py-1 text-left font-semibold text-gray-700">Factura</th>
                                                                                <th class="px-3 py-1 text-left font-semibold text-gray-700">Emisión</th>
                                                                                <th class="px-3 py-1 text-left font-semibold text-gray-700">Vencimiento</th>
                                                                                <th class="px-3 py-1 text-right font-semibold text-gray-700">Saldo</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody class="divide-y divide-gray-100">
                                                                            @foreach ($proveedor['facturas'] ?? [] as $factura)
                                                                                <tr>
                                                                                    <td class="px-3 py-1 text-gray-700">{{ $factura['numero'] ?? '' }}</td>
                                                                                    <td class="px-3 py-1 text-gray-700">{{ $factura['fecha_emision'] ?? '' }}</td>
                                                                                    <td class="px-3 py-1 text-gray-700">{{ $factura['fecha_vencimiento'] ?? '' }}</td>
                                                                                    <td class="px-3 py-1 text-right font-semibold text-gray-800">
                                                                                        ${{ number_format((float) ($factura['saldo'] ?? 0), 2, '.', ',') }}
                                                                                    </td>
                                                                                </tr>
                                                                            @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </details>
                                                        </td>
                                                        <td class="px-4 py-3 text-right font-semibold text-gray-800">
                                                            ${{ number_format((float) ($proveedor['total'] ?? 0), 2, '.', ',') }}
                                                        </td>
                                                        <td class="px-4 py-3 text-center">
                                                            <input
                                                                type="checkbox"
                                                                value="{{ $proveedor['key'] }}"
                                                                wire:model.live="selectedProviders"
                                                                class="h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                                            />
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::card>
                @empty
                    <div class="text-sm text-gray-600">
                        Seleccione una conexión y empresas para visualizar el presupuesto de pagos.
                    </div>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
