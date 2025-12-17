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
        Schema::table('solicitud_pago_detalles', function (Blueprint $table) {
            $table->string('proveedor_ruc')->nullable()->after('proveedor_nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_pago_detalles', function (Blueprint $table) {
            $table->dropColumn('proveedor_ruc');
        });
    }
};
