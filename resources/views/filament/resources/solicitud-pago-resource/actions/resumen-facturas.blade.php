<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Facturas seleccionadas</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $pagadas }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $pendientes }} pendientes</p>
        </div>
        <div class="text-right">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Total seleccionado</p>
            <p class="text-xl font-bold text-primary-600 dark:text-primary-300">${{ number_format((float) $totalSeleccionado, 2) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Monto solicitado: ${{ number_format((float) $monto, 2) }}</p>
        </div>
    </div>

    @php
        $diferenciaColor = 'text-green-700 bg-green-100 dark:text-green-300 dark:bg-green-900/40';
        $diferenciaTexto = 'El monto coincide con la selección.';

        if ($diferencia > 0.01) {
            $diferenciaColor = 'text-amber-700 bg-amber-100 dark:text-amber-200 dark:bg-amber-900/40';
            $diferenciaTexto = 'Faltan ' . number_format($diferencia, 2) . ' para cubrir todas las facturas seleccionadas.';
        }

        if ($diferencia < -0.01) {
            $diferenciaColor = 'text-rose-700 bg-rose-100 dark:text-rose-200 dark:bg-rose-900/40';
            $diferenciaTexto = 'El monto supera la selección por ' . number_format(abs($diferencia), 2) . '.';
        }
    @endphp

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-sm text-gray-700 shadow-inner dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Monto aprobado</p>
            <p class="mt-1 text-lg font-semibold">${{ number_format((float) $monto, 2) }}</p>
        </div>
        <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-sm text-gray-700 shadow-inner dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Diferencia</p>
            <p class="mt-1 text-lg font-semibold">${{ number_format((float) $diferencia, 2) }}</p>
        </div>
        <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-sm text-gray-700 shadow-inner dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Estado de selección</p>
            <p class="mt-1 text-lg font-semibold">{{ $pagadas }} / {{ $pagadas + $pendientes }}</p>
        </div>
    </div>

    <div class="mt-3 rounded-lg px-3 py-2 text-sm font-semibold {{ $diferenciaColor }}">
        {{ $diferenciaTexto }}
    </div>
</div>
