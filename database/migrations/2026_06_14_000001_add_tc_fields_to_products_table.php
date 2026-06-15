<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('commercial_name')->nullable()->after('name')
                ->comment('Nombre comercial del producto, fluye a Composición Técnica');
            $table->string('product_family')->nullable()->after('commercial_name')
                ->comment('Familia de producto, fluye a Composición Técnica');
            $table->string('springs')->nullable()->after('plazas')
                ->comment('Tipo de resortes, fluye a Composición Técnica');
            $table->string('foam_description')->nullable()->after('springs')
                ->comment('Descripción de espuma, fluye a Composición Técnica');
            $table->text('conservation_instructions')->nullable()->after('description')
                ->comment('Instrucciones de conservación, fluye a Composición Técnica');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'commercial_name',
                'product_family',
                'springs',
                'foam_description',
                'conservation_instructions',
            ]);
        });
    }
};
