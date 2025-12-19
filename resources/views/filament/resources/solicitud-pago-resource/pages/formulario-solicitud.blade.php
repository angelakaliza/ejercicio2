<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $facturasDisponibles = $this->data['facturas_disponibles'] ?? [];
        @endphp

        <x-filament::section>
            <x-slot name="heading">
                Configuración y filtros
            </x-slot>
            <x-slot name="description">
                Defina la conexión y el contexto (multiempresa y multisucursal) para cargar las facturas disponibles y gestionar la solicitud de pago.
            </x-slot>

            {{ $this->form }}
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Detalle de facturas del proveedor
            </x-slot>
            <x-slot name="description">
                Revise y seleccione las facturas que formarán parte de la Solicitud de Pago. La estructura replica el flujo de Presupuesto de pago a proveedores, respetando multiempresa, multisucursal y multiproveedor.
            </x-slot>

            @if (filled($facturasDisponibles))
                <div class="space-y-4">
                    @foreach ($facturasDisponibles as $empresa)
                        <x-filament::card>
                            <div class="text-base font-semibold text-gray-700">
                                {{ $empresa['empresa_nombre'] ?? $empresa['empresa_codigo'] ?? 'Empresa' }}
                            </div>

                            <div class="mt-3 space-y-3">
                                @foreach ($empresa['sucursales'] ?? [] as $sucursal)
                                    <div class="overflow-hidden rounded-xl border border-gray-200">
                                        <div class="bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-700">
                                            {{ $sucursal['sucursal_nombre'] ?? $sucursal['sucursal_codigo'] ?? 'Sucursal' }}
                                        </div>

                                        @foreach ($sucursal['proveedores'] ?? [] as $proveedor)
                                            <div class="border-t border-gray-200 px-4 py-3">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <div class="font-semibold text-gray-800">{{ $proveedor['proveedor_nombre'] ?? $proveedor['proveedor_codigo'] ?? 'Proveedor' }}</div>
                                                        <div class="text-xs text-gray-500">RUC: {{ $proveedor['proveedor_ruc'] ?? 'N/D' }}</div>
                                                    </div>
                                                    <div class="text-sm font-semibold text-amber-600">
                                                        Total proveedor: ${{ number_format((float) ($proveedor['total'] ?? 0), 2, '.', ',') }}
                                                    </div>
                                                </div>

                                                <div class="mt-2 overflow-hidden rounded-md border border-gray-200">
                                                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                                                        <thead class="bg-slate-50">
                                                            <tr>
                                                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Seleccionar</th>
                                                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Factura</th>
                                                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Emisión</th>
                                                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Vencimiento</th>
                                                                <th class="px-3 py-2 text-right font-semibold text-gray-700">Saldo</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100 bg-white">
                                                            @foreach ($proveedor['facturas'] ?? [] as $factura)
                                                                <tr>
                                                                    <td class="px-3 py-2 text-left">
                                                                        {{ $factura['seleccionado'] ? '✔️' : '—' }}
                                                                    </td>
                                                                    <td class="px-3 py-2">{{ $factura['numero'] ?? '' }}</td>
                                                                    <td class="px-3 py-2">{{ $factura['fecha_emision'] ?? '' }}</td>
                                                                    <td class="px-3 py-2">{{ $factura['fecha_vencimiento'] ?? '' }}</td>
                                                                    <td class="px-3 py-2 text-right font-semibold text-gray-800">${{ number_format((float) ($factura['saldo'] ?? 0), 2, '.', ',') }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </x-filament::card>
                    @endforeach
                </div>
            @else
                <div class="text-sm text-gray-600">
                    Seleccione una conexión y contexto para cargar facturas pendientes.
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
