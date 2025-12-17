<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'motivo_correccion',
        'tipo_solicitud',
        'empresas_seleccionadas',
        'sucursales_seleccionadas',
        'proveedores_seleccionados',
        'total',
        'estado',
        'aprobado_por',
        'aprobado_en',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total' => 'float',
        'empresas_seleccionadas' => 'array',
        'sucursales_seleccionadas' => 'array',
        'proveedores_seleccionados' => 'array',
        'aprobado_en' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function detalles()
    {
        return $this->hasMany(SolicitudPagoDetalle::class);
    }

    public function adjuntos()
    {
        return $this->hasMany(SolicitudPagoAdjunto::class);
    }

    public function aprobaciones()
    {
        return $this->hasMany(AprobacionPago::class);
    }
}
