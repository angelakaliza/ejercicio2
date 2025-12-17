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
        Schema::create('aprobacion_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_pago_id')->constrained('solicitud_pagos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('monto_aprobado', 15, 2);
            $table->unsignedInteger('facturas_pagadas')->default(0);
            $table->unsignedInteger('facturas_pendientes')->default(0);
            $table->timestamps();
        });

        Schema::create('aprobacion_pago_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aprobacion_pago_id')->constrained('aprobacion_pagos')->cascadeOnDelete();
            $table->foreignId('solicitud_pago_detalle_id')->constrained('solicitud_pago_detalles')->cascadeOnDelete();
            $table->decimal('monto_aprobado', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aprobacion_pago_detalles');
        Schema::dropIfExists('aprobacion_pagos');
    }
};
