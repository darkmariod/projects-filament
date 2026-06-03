<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranties', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_warranties_status_created');
        });

        Schema::table('label_batches', function (Blueprint $table) {
            $table->index(['created_at'], 'idx_label_batches_created');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['active', 'product_code'], 'idx_products_active_code');
        });
    }

    public function down(): void
    {
        Schema::table('warranties', function (Blueprint $table) {
            $table->dropIndex('idx_warranties_status_created');
        });

        Schema::table('label_batches', function (Blueprint $table) {
            $table->dropIndex('idx_label_batches_created');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_active_code');
        });
    }
};
