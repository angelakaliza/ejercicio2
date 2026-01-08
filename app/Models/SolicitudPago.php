<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudPago extends Model
{
    protected $fillable = [
        'id_empresa',
        'amdg_id_empresa',
        'amdg_id_sucursal',
        'proveedor_id',
        'proveedor_nombre',
        'fecha',
        'motivo',
        'tipo_solicitud',
        'empresas_seleccionadas',
        'sucursales_seleccionadas',
        'proveedores_seleccionados',
        'total',
        'monto_aprobado',
        'monto_estimado',
        'monto_utilizado',
        'aprobado_por_id',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total' => 'float',
        'monto_aprobado' => 'float',
        'monto_estimado' => 'float',
        'monto_utilizado' => 'float',
        'empresas_seleccionadas' => 'array',
        'sucursales_seleccionadas' => 'array',
        'proveedores_seleccionados' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    public function detalles()
    {
        return $this->hasMany(SolicitudPagoDetalle::class);
    }

    public function adjuntos()
    {
        return $this->hasMany(SolicitudPagoAdjunto::class);
    }
}
