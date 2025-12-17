<div class="space-y-3">
    <div class="text-sm text-gray-600">
        <span class="font-semibold">Solicitud:</span> #{{ $solicitud->id }}
    </div>
    <div class="rounded-lg bg-orange-50 p-4 text-sm text-gray-800">
        <p class="font-semibold text-orange-700">Motivo solicitado</p>
        <p class="mt-2 whitespace-pre-line">{{ $solicitud->motivo_correccion }}</p>
    </div>
    <div class="text-xs text-gray-500">
        Recuerde realizar las correcciones necesarias y volver a editar la solicitud antes de reenviar para aprobación.
    </div>
</div>
