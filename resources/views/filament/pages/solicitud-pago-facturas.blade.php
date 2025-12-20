<x-filament-panels::page>
    <div class="space-y-6">
        @if ($this->solicitud)
            <x-filament::section>
                <x-slot name="heading">
                    Resumen de la solicitud
                </x-slot>

               @php
        $conexionesIds = $this->filters['conexiones'] ?? [$this->solicitud->id_empresa];
        $conexionesNombres = \App\Models\Empresa::query()
            ->whereIn('id', $conexionesIds)
            ->pluck('nombre_empresa')
            ->implode(', ');
    @endphp

                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[180px] rounded-lg border border-gray-200 bg-white p-4">
                        <div class="text-sm text-gray-500">Estado</div>
                        <div class="mt-1 text-lg font-semibold text-gray-800">{{ $this->solicitud->estado }}</div>
                    </div>

                    <div class="flex-1 min-w-[180px] rounded-lg border border-gray-200 bg-white p-4">
                        <div class="text-sm text-gray-500">Fecha</div>
                        <div class="mt-1 text-lg font-semibold text-gray-800">
                            {{ optional($this->solicitud->fecha)->format('Y-m-d') }}
                        </div>
                    </div>

                    <div class="flex-1 min-w-[180px] rounded-lg border border-gray-200 bg-white p-4">
                        <div class="text-sm text-gray-500">Monto estimado</div>
                        <div class="mt-1 text-lg font-semibold text-gray-800">
                            ${{ number_format($this->solicitud->total, 2, '.', ',') }}
                        </div>
                    </div>

                    <div class="flex-1 min-w-[180px] rounded-lg border border-gray-200 bg-white p-4">
                        <div class="text-sm text-gray-500">Monto aprobado</div>
                        <div class="mt-1 text-lg font-semibold text-gray-800">
                            ${{ number_format((float) ($this->filters['monto_aprobado'] ?? 0), 2, '.', ',') }}
                        </div>
                    </div>


                </div>
            </x-filament::section>
        @endif


        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">
                Selección de facturas
            </x-slot>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-600">
                    Seleccione facturas para generar la solicitud de pago.
                </div>
                <div class="text-right">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">Total de todas las
                        facturas</div>
                    <div class="text-lg font-semibold text-amber-600">
                        ${{ number_format($this->totalFacturas, 2, '.', ',') }}
                    </div>
                </div>
            </div>

            <div class="mt-3 grid gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 sm:grid-cols-4">
                <div class="font-semibold">
                    Total de todas las facturas:
                    ${{ number_format($this->totalFacturas, 2, '.', ',') }}
                </div>

                <div class="font-semibold">
                    Monto aprobado:
                    ${{ number_format((float) ($this->filters['monto_aprobado'] ?? 0), 2, '.', ',') }}
                </div>

                <div class="font-semibold">
                    Abono en uso:
                    ${{ number_format($this->abonoEnUso, 2, '.', ',') }}
                </div>

                <div class="font-semibold">
                    Disponible:
                    ${{ number_format($this->presupuestoDisponible, 2, '.', ',') }}
                </div>
            </div>


            <div class="mt-4 space-y-4">
                @php
                    $allowSelection = ! $this->solicitud;
                @endphp

                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative w-full">

                        <input type="text" wire:model.live.debounce.300ms="search"
                            placeholder="Buscar proveedor, factura o RUC…"
                            class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-3 text-sm focus:border-amber-500 focus:ring-amber-500" />
                    </div>

                    <button type="button" wire:click="$set('search','')"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                        Limpiar
                    </button>
                </div>


                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    @php
                        $columnsCount = $allowSelection ? 4 : 3;
                    @endphp

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">
                                        <button type="button" wire:click="sortBy('proveedor_nombre')"
                                            class="flex items-center gap-1">
                                            Proveedor
                                            @if ($sortField === 'proveedor_nombre')
                                                <span class="text-xs text-amber-600">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                            @endif
                                        </button>
                                    </th>
                                    <th class="px-4 py-2 text-right font-semibold text-gray-700">
                                        <button type="button" wire:click="sortBy('total')" class="flex items-center gap-1 float-right">
                                            Total
                                            @if ($sortField === 'total')
                                                <span class="text-xs text-amber-600">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                            @endif
                                        </button>
                                    </th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Facturas</th>
                                    @if ($allowSelection)
                                        <th class="px-4 py-2 text-center font-semibold text-gray-700">
                                            <button type="button" wire:click="sortBy('selected')"
                                                class="flex items-center justify-center gap-1 w-full">
                                                Seleccionar
                                                @if ($sortField === 'selected')
                                                    <span class="text-xs text-amber-600">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                                @endif
                                            </button>
                                        </th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($this->providersPaginated as $proveedor)
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
                                                <span
                                                    class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">{{ $proveedor['facturas_count'] ?? 0 }}
                                                    factura(s)</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-800">
                                            ${{ number_format((float) ($proveedor['total'] ?? 0), 2, '.', ',') }}</td>
                                        <td class="px-4 py-3">
                                            <details wire:key="prov-{{ $proveedor['key'] }}" x-data="{ open: $wire.entangle('openProviders.{{ $proveedor['key'] }}').live }"
                                                :open="open" @toggle="open = $event.target.open"
                                                class="rounded-md border border-gray-200 bg-slate-50 p-3">

                                                <summary class="cursor-pointer text-sm font-semibold text-slate-700">
                                                    Ver detalle agrupado
                                                </summary>
                                                <div class="mt-2 space-y-3">
                                                    @foreach ($proveedor['empresas'] ?? [] as $empresa)
                                                        <div class="rounded-lg border border-slate-200 bg-white">
                                                            <div
                                                                class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700">
                                                                <span>{{ $empresa['conexion_nombre'] ?? 'Conexión' }} ·
                                                                    {{ $empresa['empresa_nombre'] ?? $empresa['empresa_codigo'] }}</span>
                                                                <span
                                                                    class="text-[11px] font-medium text-slate-500">{{ count($empresa['sucursales'] ?? []) }}
                                                                    sucursal(es)</span>
                                                            </div>
                                                            <div class="space-y-2 p-3">
                                                                @foreach ($empresa['sucursales'] ?? [] as $sucursal)
                                                                    <div class="rounded-md border border-slate-200">
                                                                        <div
                                                                            class="flex items-center justify-between bg-slate-50 px-3 py-1.5 text-[11px] font-semibold text-slate-700">
                                                                            <span>{{ $sucursal['sucursal_nombre'] ?? $sucursal['sucursal_codigo'] }}</span>
                                                                            <span
                                                                                class="text-[11px] font-medium text-slate-500">{{ count($sucursal['facturas'] ?? []) }}
                                                                                factura(s)</span>
                                                                        </div>
                                                                        <div class="overflow-x-auto">
                                                                            <table
                                                                                class="min-w-full divide-y divide-gray-200 text-xs">
                                                                                <thead class="bg-white">
                                                                                    <tr>
                                                                                        <th
                                                                                            class="px-3 py-1 text-left font-semibold text-gray-700">
                                                                                            Factura</th>
                                                                                        <th
                                                                                            class="px-3 py-1 text-left font-semibold text-gray-700">
                                                                                            Emisión</th>
                                                                                        <th
                                                                                            class="px-3 py-1 text-left font-semibold text-gray-700">
                                                                                            Vencimiento</th>
                                                                                        <th
                                                                                            class="px-3 py-1 text-right font-semibold text-gray-700">
                                                                                            Saldo</th>
                                                                                        <th
                                                                                            class="px-3 py-1 text-right font-semibold text-gray-700">
                                                                                            Abono</th>
                                                                                        @if ($allowSelection)
                                                                                            <th
                                                                                                class="px-3 py-1 text-center font-semibold text-gray-700">
                                                                                                Seleccionar</th>
                                                                                        @endif
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody class="divide-y divide-gray-100">
                                                                                    @foreach ($sucursal['facturas'] ?? [] as $factura)
                                                                                        <tr
                                                                                            wire:key="fac-{{ $factura['key'] }}">

                                                                                            <td
                                                                                                class="px-3 py-1 text-gray-700">
                                                                                                {{ $factura['numero'] ?? '' }}
                                                                                            </td>
                                                                                            <td
                                                                                                class="px-3 py-1 text-gray-700">
                                                                                                {{ $factura['fecha_emision'] ?? '' }}
                                                                                            </td>
                                                                                            <td
                                                                                                class="px-3 py-1 text-gray-700">
                                                                                                {{ $factura['fecha_vencimiento'] ?? '' }}
                                                                                            </td>
                                                                                            <td
                                                                                                class="px-3 py-1 text-right font-semibold text-gray-800">
                                                                                                ${{ number_format((float) ($factura['saldo'] ?? 0), 2, '.', ',') }}
                                                                                            </td>
                                                                                            <td
                                                                                                class="px-3 py-1 text-right">
                                                                                                @php

                                                                                                    $key =
                                                                                                        $factura['key'];
                                                                                                    $saldoFactura =
                                                                                                        (float) ($factura[
                                                                                                            'saldo'
                                                                                                        ] ?? 0);
                                                                                                    $abonoActual =
                                                                                                        (float) ($this
                                                                                                            ->invoiceAbonos[
                                                                                                            $key
                                                                                                        ] ?? 0);

                                                                                                    $abonoActual =
                                                                                                        (float) ($this
                                                                                                            ->invoiceAbonos[
                                                                                                            $key
                                                                                                        ] ?? 0);

                                                                                                    // Disponible real para ESTA factura (le sumamos su propio abono)
                                                                                                    $disponibleParaFactura = max(
                                                                                                        0,
                                                                                                        $this->presupuestoDisponible +
                                                                                                            $abonoActual,
                                                                                                    );

                                                                                                    // Máximo permitido = saldo factura VS disponible
                                                                                                    $maximoPermitido = min(
                                                                                                        (float) ($factura[
                                                                                                            'saldo'
                                                                                                        ] ?? 0),
                                                                                                        $disponibleParaFactura,
                                                                                                    );
                                                                                                @endphp


                                                                                                <div x-data="{
                                                                                                    key: @js($key),
                                                                                                    saldo: {{ $saldoFactura }},
                                                                                                    draft: @js(number_format($abonoActual, 2, '.', '')),

                                                                                                    sanitize(val) {
                                                                                                        val = (val ?? '').toString();
                                                                                                        val = val.replace(/[^0-9.,]/g, '');

                                                                                                        // normaliza separador decimal (solo uno)
                                                                                                        const d = val.indexOf('.');
                                                                                                        const c = val.indexOf(',');
                                                                                                        const sep = (d === -1) ? c : (c === -1 ? d : Math.min(d, c));

                                                                                                        if (sep !== -1) {
                                                                                                            const head = val.slice(0, sep);
                                                                                                            const tail = val.slice(sep + 1).replace(/[.,]/g, '');
                                                                                                            val = head + '.' + tail;
                                                                                                        }

                                                                                                        return val;
                                                                                                    },

                                                                                                    toNumber(val) {
                                                                                                        const s = this.sanitize(val);
                                                                                                        if (s === '') return null; // 👈 importante: no fuerces 0 mientras escribe vacío
                                                                                                        const n = parseFloat(s);
                                                                                                        return isNaN(n) ? null : n;
                                                                                                    },

                                                                                                    maxPermitido() {
                                                                                                        // monto aprobado viene en filters.monto_aprobado (statePath filters)
                                                                                                        const aprobado = parseFloat($wire.filters?.monto_aprobado ?? 0) || 0;

                                                                                                        // suma de abonos de TODAS menos esta
                                                                                                        const abonos = $wire.invoiceAbonos ?? {};
                                                                                                        let totalSinEsta = 0;

                                                                                                        for (const [k, v] of Object.entries(abonos)) {
                                                                                                            if (k === this.key) continue;
                                                                                                            const n = parseFloat(String(v).replace(',', '.')) || 0;
                                                                                                            totalSinEsta += Math.max(0, n);
                                                                                                        }

                                                                                                        const disponible = Math.max(0, aprobado - totalSinEsta);

                                                                                                        // máximo permitido = min(saldoFactura, disponible)
                                                                                                        return Math.max(0, Math.min(this.saldo, disponible));
                                                                                                    },


                                                                                                    clamp(n) {
                                                                                                        n = Math.max(0, n);
                                                                                                        n = Math.min(this.maxPermitido(), n);
                                                                                                        return Math.round(n * 100) / 100;
                                                                                                    },

                                                                                                    commit() {
                                                                                                        const n0 = this.toNumber(this.draft);
                                                                                                        if (n0 === null) return; // 👈 si aún no es número válido, no lo aplastes a 0

                                                                                                        const n = this.clamp(n0);
                                                                                                        this.draft = n.toFixed(2);
                                                                                                        $wire.set(`invoiceAbonos.${this.key}`, n);
                                                                                                    }
                                                                                                }"
                                                                                                    x-on:click.stop
                                                                                                    x-on:keydown.stop>
                                                                                                    <input
                                                                                                        type="text"
                                                                                                        inputmode="decimal"
                                                                                                        x-model="draft"
                                                                                                        x-on:keydown="if ($event.key === '-' || $event.key === 'e' || $event.key === 'E') $event.preventDefault();"
                                                                                                        x-on:input="draft = sanitize(draft)"
                                                                                                        x-on:input.debounce.400ms="commit()"
                                                                                                        x-on:blur="commit()"
                                                                                                        class="w-28 rounded border border-gray-300 px-2 py-1 text-right text-sm focus:border-amber-500 focus:ring-amber-500"
                                                                                                        @disabled($allowSelection && !in_array($key, $this->selectedInvoices)) />

                                                                                                    <div
                                                                                                        class="text-[11px] text-gray-500">
                                                                                                        Máx: $<span
                                                                                                            x-text="maxPermitido().toFixed(2)"></span>
                                                                                                    </div>
                                                                                                </div>


                                                                                            </td>
                                                                                            @if ($allowSelection)
                                                                                                <td
                                                                                                    class="px-3 py-1 text-center">
                                                                                                    <input type="checkbox"
                                                                                                        value="{{ $factura['key'] }}"
                                                                                                        wire:model.live="selectedInvoices"
                                                                                                        class="h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                                                                                        @disabled(!in_array($factura['key'], $this->selectedInvoices) && $this->presupuestoDisponible <= 0) />

                                                                                                </td>
                                                                                            @endif
                                                                                        </tr>
                                                                                    @endforeach
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </details>
                                        </td>
                                        @if ($allowSelection)
                                            <td class="px-4 py-3 text-center text-xs text-gray-500">Selección por factura
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $columnsCount }}" class="px-4 py-4 text-center text-sm text-gray-600">
                                            Seleccione filtros para visualizar las facturas disponibles.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 bg-white px-4 py-3">
                        {{ $this->providersPaginated->links() }}
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
