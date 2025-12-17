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
        Schema::create('solicitud_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_empresa')->constrained('empresas')->cascadeOnDelete();
            $table->string('amdg_id_empresa');
            $table->string('amdg_id_sucursal');
            $table->string('proveedor_id');
            $table->string('proveedor_nombre');
            $table->date('fecha');
            $table->text('motivo')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('estado')->default('PENDIENTE');
            $table->timestamps();
        });

        Schema::create('solicitud_pago_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_pago_id')->constrained('solicitud_pagos')->cascadeOnDelete();
            $table->string('numero_factura');
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('monto', 15, 2)->default(0);
            $table->decimal('saldo', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_pago_detalles');
        Schema::dropIfExists('solicitud_pagos');
    }
};
