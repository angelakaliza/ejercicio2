<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_pago_detalles', function (Blueprint $table) {
            $table->decimal('total', 18, 2)->default(0)->after('monto');
            $table->string('estado_abono')->default('SIN_ABONO')->after('saldo_pendiente');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_pago_detalles', function (Blueprint $table) {
            $table->dropColumn(['total', 'estado_abono']);
        });
    }
};
