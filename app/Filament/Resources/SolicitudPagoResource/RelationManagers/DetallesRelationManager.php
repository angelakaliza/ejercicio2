<?php

namespace App\Filament\Resources\SolicitudPagoResource\RelationManagers;

use App\Filament\Resources\SolicitudPagoResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Grouping\Group;

class DetallesRelationManager extends RelationManager
{
    protected static string $relationship = 'detalles';

    protected static ?string $title = 'Facturas solicitadas';

    public function table(Table $table): Table
    {
        $empresaOptions = SolicitudPagoResource::getEmpresasOptions($this->getOwnerRecord()->id_empresa);
        $empresasSeleccionadas = $this->getOwnerRecord()->empresas_seleccionadas ?? array_keys($empresaOptions);
        $sucursalOptions = SolicitudPagoResource::getSucursalesOptions($this->getOwnerRecord()->id_empresa, $empresasSeleccionadas);

        $empresaLabel = fn($record) => $empresaOptions[$record->amdg_id_empresa] ?? $record->amdg_id_empresa;
        $sucursalLabel = fn($record) => $sucursalOptions[$record->amdg_id_sucursal] ?? $record->amdg_id_sucursal;

        return $table
            ->columns([
                TextColumn::make('amdg_id_empresa')
                    ->label('Empresa')
                    ->formatStateUsing(fn($state, $record) => $empresaLabel($record)),

                TextColumn::make('amdg_id_sucursal')
                    ->label('Sucursal')
                    ->formatStateUsing(fn($state, $record) => $sucursalLabel($record))
                    ->toggleable(),

                TextColumn::make('proveedor_nombre')
                    ->label('Proveedor')
                    ->searchable(),

                TextColumn::make('numero_factura')
                    ->label('N° Factura')
                    ->searchable(),

                TextColumn::make('fecha_emision')
                    ->label('Emisión')
                    ->date('Y-m-d'),

                TextColumn::make('fecha_vencimiento')
                    ->label('Vence')
                    ->date('Y-m-d'),

                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->money('USD')
                    ->summarize([
                        Sum::make()->label('Total'),
                    ]),
            ])
            ->groups([
                Group::make('amdg_id_empresa')
                    ->label('Empresa')
                    ->getTitleFromRecordUsing(fn($record) => $empresaLabel($record))
                    ->collapsible(),
                Group::make('amdg_id_sucursal')
                    ->label('Sucursal')
                    ->getTitleFromRecordUsing(fn($record) => $sucursalLabel($record))
                    ->collapsible(),
                Group::make('proveedor_codigo')
                    ->label('Proveedor')
                    ->getTitleFromRecordUsing(fn($record) => $record->proveedor_nombre ?? $record->proveedor_codigo)
                    ->collapsible(),
            ])
            ->defaultGroup('amdg_id_empresa')
            // Solo lectura (ver)
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
