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
            $table->foreignId('aprobado_por')->nullable()->after('estado')->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_en')->nullable()->after('aprobado_por');
            $table->text('motivo_correccion')->nullable()->after('motivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprobado_por');
            $table->dropColumn('aprobado_en');
            $table->dropColumn('motivo_correccion');
        });
    }
};
