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
            $table->decimal('monto_aprobado', 15, 2)->nullable()->after('total');
            $table->decimal('monto_estimado', 15, 2)->nullable()->after('monto_aprobado');
            $table->foreignId('usuario_aprobador_id')->nullable()->after('monto_estimado')->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usuario_aprobador_id');
            $table->dropColumn(['monto_aprobado', 'monto_estimado']);
        });
    }
};
