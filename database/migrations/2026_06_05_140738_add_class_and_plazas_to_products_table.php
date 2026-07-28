<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('class')->nullable()->after('measurements_text')
                ->comment('Clase del producto: Clase A, Clase B, etc.');
            $table->string('plazas')->nullable()->after('class')
                ->comment('Cantidad de plazas: 1 PLZ, 1 1/2 PLZ, etc.');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['class', 'plazas']);
        });
    }
};
