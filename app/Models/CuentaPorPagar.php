<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaPorPagar extends Model
{
    protected $table = 'saedmcp';

    public $timestamps = false;

    protected $primaryKey = 'dmcp_cod_tran';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
