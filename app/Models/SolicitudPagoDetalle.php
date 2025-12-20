<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudPagoDetalle extends Model
{
    protected $fillable = [
        'id_empresa',
        'amdg_id_empresa',
        'amdg_id_sucursal',
        'proveedor_codigo',
        'proveedor_nombre',
        'proveedor_ruc',
        'solicitud_pago_id',
        'numero_factura',
        'fecha_emision',
        'fecha_vencimiento',
        'monto',
        'total',
        'saldo',
        'abono',
        'saldo_pendiente',
        'estado_abono',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'monto' => 'float',
        'total' => 'float',
        'saldo' => 'float',
        'abono' => 'float',
        'saldo_pendiente' => 'float',
    ];

    public function solicitudPago()
    {
        return $this->belongsTo(SolicitudPago::class);
    }
}
