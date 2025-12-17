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
            $table->json('empresas_seleccionadas')->nullable()->after('amdg_id_sucursal');
            $table->json('sucursales_seleccionadas')->nullable()->after('empresas_seleccionadas');
            $table->json('proveedores_seleccionados')->nullable()->after('sucursales_seleccionadas');
            $table->string('tipo_solicitud')->default('Pago de Facturas')->after('motivo');
        });

        Schema::table('solicitud_pago_detalles', function (Blueprint $table) {
            $table->foreignId('id_empresa')->after('id')->constrained('empresas')->cascadeOnDelete();
            $table->string('amdg_id_empresa')->after('id_empresa');
            $table->string('amdg_id_sucursal')->nullable()->after('amdg_id_empresa');
            $table->string('proveedor_codigo')->after('amdg_id_sucursal');
            $table->string('proveedor_nombre')->after('proveedor_codigo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_pago_detalles', function (Blueprint $table) {
            $table->dropColumn([
                'proveedor_nombre',
                'proveedor_codigo',
                'amdg_id_sucursal',
                'amdg_id_empresa',
                'id_empresa',
            ]);
        });

        Schema::table('solicitud_pagos', function (Blueprint $table) {
            $table->dropColumn([
                'proveedores_seleccionados',
                'sucursales_seleccionadas',
                'empresas_seleccionadas',
                'tipo_solicitud',
            ]);
        });
    }
};
