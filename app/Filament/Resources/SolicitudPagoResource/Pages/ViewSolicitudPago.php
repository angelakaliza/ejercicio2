<?php

namespace App\Filament\Resources\SolicitudPagoResource\Pages;

use App\Filament\Resources\SolicitudPagoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSolicitudPago extends ViewRecord
{
    protected static string $resource = SolicitudPagoResource::class;

    protected static string $view = 'filament.resources.solicitud-pago-resource.pages.formulario-solicitud';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
