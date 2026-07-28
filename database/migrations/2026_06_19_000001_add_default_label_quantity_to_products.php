<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('default_label_quantity')
                ->unsigned()
                ->nullable()
                ->after('active')
                ->comment('Cantidad default de etiquetas al auto-crear el batch');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('default_label_quantity');
        });
    }
};
