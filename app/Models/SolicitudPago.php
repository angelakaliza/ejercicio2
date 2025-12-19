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
        'tipo_solicitud',
        'empresas_seleccionadas',
        'sucursales_seleccionadas',
        'proveedores_seleccionados',
        'total',
        'estado',
        'aprobado_por',
        'motivo_correccion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total' => 'float',
        'empresas_seleccionadas' => 'array',
        'sucursales_seleccionadas' => 'array',
        'proveedores_seleccionados' => 'array',
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

    public function aprobador()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function aprobaciones()
    {
        return $this->hasMany(AprobacionPago::class);
    }
}
