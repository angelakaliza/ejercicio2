<?php

namespace App\Filament\Resources\SolicitudPago2Resource\Pages;

use App\Filament\Resources\SolicitudPago2Resource;
use App\Filament\Resources\SolicitudPagoResource\Pages\CreateSolicitudPago;

class CreateSolicitudPago2 extends CreateSolicitudPago
{
    protected static string $resource = SolicitudPago2Resource::class;

    protected static string $view = 'filament.resources.solicitud-pago2-resource.pages.create-solicitud-pago2';
}
