<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('solicitud_pagos', function (Blueprint $table) {
           // $table->decimal('monto_aprobado', 15, 2)->default(0)->after('total');
            $table->decimal('monto_estimado', 15, 2)->default(0)->after('monto_aprobado');
            $table->foreignId('aprobado_por_id')->nullable()->after('monto_estimado')->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprobado_por_id');
            $table->dropColumn(['monto_estimado', 'monto_aprobado']);
        });
    }
};
