<?php

namespace App\Filament\Resources\SolicitudPagoResource\Pages;

use App\Filament\Resources\SolicitudPagoResource;
use App\Filament\Pages\SolicitudPagoFacturas;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSolicitudPagos extends ListRecords
{
    protected static string $resource = SolicitudPagoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('nuevo')
                ->label('New Solicitud Pago')
                ->icon('heroicon-o-document-plus')
                ->url(SolicitudPagoFacturas::getUrl()),
        ];
    }
}
