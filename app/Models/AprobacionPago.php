<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AprobacionPago extends Model
{
    protected $fillable = [
        'solicitud_pago_id',
        'user_id',
        'monto_aprobado',
        'facturas_seleccionadas',
        'facturas_pagadas',
        'facturas_pendientes',
    ];

    protected $casts = [
        'monto_aprobado' => 'float',
        'facturas_seleccionadas' => 'array',
        'facturas_pagadas' => 'array',
        'facturas_pendientes' => 'array',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudPago::class, 'solicitud_pago_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
