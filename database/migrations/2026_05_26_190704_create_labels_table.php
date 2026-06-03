<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_batch_id')->constrained('label_batches')->onDelete('restrict');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->string('serial')->unique();
            $table->integer('sequence_number');
            $table->string('barcode')->nullable();
            $table->string('qr_url');
            $table->longText('zpl_generated')->nullable();
            $table->string('status')->default('available');
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labels');
    }
};