<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La etiqueta imprime tres responsables de control de calidad: operador de
 * ensamble, cerrador y trazabilidad. Solo existía `operator`, así que se
 * agregan los dos que faltaban.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_batches', function (Blueprint $table) {
            $table->string('closer')->nullable()->after('operator');
            $table->string('tracer')->nullable()->after('closer');
        });
    }

    public function down(): void
    {
        Schema::table('label_batches', function (Blueprint $table) {
            $table->dropColumn(['closer', 'tracer']);
        });
    }
};
