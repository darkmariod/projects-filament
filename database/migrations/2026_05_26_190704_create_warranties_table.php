<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->unique()->constrained('labels')->onDelete('restrict');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->string('store_name');
            $table->string('invoice_number');
            $table->date('purchase_date');
            $table->date('warranty_start_date');
            $table->date('warranty_end_date');
            $table->string('pdf_path')->nullable();
            $table->string('status')->default('active');
            $table->boolean('terms_accepted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranties');
    }
};