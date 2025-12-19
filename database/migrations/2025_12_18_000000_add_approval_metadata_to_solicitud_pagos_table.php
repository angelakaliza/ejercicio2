<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_pagos', function (Blueprint $table) {
            $table->decimal('monto_aprobado', 15, 2)->nullable()->after('total');
            $table->foreignId('aprobado_por')->nullable()->after('monto_aprobado')->constrained('users')->nullOnDelete();
            $table->text('motivo_correccion')->nullable()->after('aprobado_por');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_pagos', function (Blueprint $table) {
            $table->dropForeign(['aprobado_por']);
            $table->dropColumn(['monto_aprobado', 'aprobado_por', 'motivo_correccion']);
        });
    }
};
