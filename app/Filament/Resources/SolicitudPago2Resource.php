<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SolicitudPago2Resource\Pages;

class SolicitudPago2Resource extends SolicitudPagoResource
{
    protected static ?string $slug = 'solicitudes-pago-2';

    protected static ?string $navigationLabel = 'Solicitudes de Pago 2';

    protected static ?string $pluralLabel = 'Solicitudes de Pago 2';

    protected static ?string $modelLabel = 'Solicitud de Pago 2';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSolicitudPago2s::route('/'),
            'create' => Pages\CreateSolicitudPago2::route('/create'),
            'view' => Pages\ViewSolicitudPago2::route('/{record}'),
            'edit' => Pages\EditSolicitudPago2::route('/{record}/edit'),
        ];
    }
}
