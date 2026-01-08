<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_pago_detalles', function (Blueprint $table) {
            $table->decimal('abono', 18, 2)->default(0)->after('saldo');
            $table->decimal('saldo_pendiente', 18, 2)->default(0)->after('abono');
        });

        Schema::table('solicitud_pagos', function (Blueprint $table) {
            $table->decimal('monto_utilizado', 18, 2)->default(0)->after('monto_estimado');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_pago_detalles', function (Blueprint $table) {
            $table->dropColumn(['abono', 'saldo_pendiente']);
        });

        Schema::table('solicitud_pagos', function (Blueprint $table) {
            $table->dropColumn('monto_utilizado');
        });
    }
};
