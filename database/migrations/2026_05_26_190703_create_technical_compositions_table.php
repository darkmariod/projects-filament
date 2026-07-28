<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_compositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->onDelete('restrict');
            $table->string('commercial_name');
            $table->string('product_family')->nullable();
            $table->string('cover_material')->nullable();
            $table->string('springs')->nullable();
            $table->string('foam_description')->nullable();
            $table->string('support_material')->nullable();
            $table->text('general_composition')->nullable();
            $table->text('conservation_instructions')->nullable();
            $table->text('legal_text')->nullable();
            $table->string('inen_standard')->nullable();
            $table->string('manufacturing_country')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('manufacturer_ruc')->nullable();
            $table->string('manufacturer_address')->nullable();
            $table->string('website')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_compositions');
    }
};