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
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('monto_aprobado', 15, 2);
            $table->json('facturas_seleccionadas')->nullable();
            $table->json('facturas_pagadas')->nullable();
            $table->json('facturas_pendientes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aprobacion_pagos');
    }
};
