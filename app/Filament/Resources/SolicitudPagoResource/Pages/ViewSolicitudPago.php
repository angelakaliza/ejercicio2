<?php

namespace App\Filament\Resources\SolicitudPagoResource\Pages;

use App\Filament\Resources\SolicitudPagoResource;
use App\Filament\Resources\SolicitudPagoResource\Pages\Concerns\UsesSolicitudPagoFormView;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSolicitudPago extends ViewRecord
{
    use UsesSolicitudPagoFormView;

    protected static string $resource = SolicitudPagoResource::class;

    protected static string $view = 'filament.resources.solicitud-pago-resource.pages.solicitud-pago-form';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
