<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->string('internal_batch_code')->unique();
            $table->string('customer_batch_number');
            $table->date('customer_batch_date');
            $table->integer('quantity');
            $table->string('operator')->nullable();
            $table->text('observations')->nullable();
            $table->string('serial_from')->nullable();
            $table->string('serial_to')->nullable();
            $table->foreignId('generated_by_user_id')->constrained('users')->onDelete('restrict');
            $table->timestamp('generated_at')->nullable();
            $table->string('status')->default('generated');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_batches');
    }
};