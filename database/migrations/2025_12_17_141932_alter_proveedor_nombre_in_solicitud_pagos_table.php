<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitud_pagos', function (Blueprint $table) {
            $table->text('proveedor_nombre')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_pagos', function (Blueprint $table) {
            $table->string('proveedor_nombre', 255)->nullable()->change();
        });
    }
};
