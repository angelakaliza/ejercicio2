<x-filament::page>
    <div class="space-y-6">
        <div>
            {{ $this->form }}
        </div>

        <x-filament::section>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-50">
                        Selección de proveedores
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Marca uno o varios proveedores directamente desde la tabla agrupada para consolidar sus facturas.
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600 dark:text-gray-300">Total seleccionado</p>
                    <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                        {{ number_format($this->selectedProvidersTotal, 2, '.', ',') }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament::page>
