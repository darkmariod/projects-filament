<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Labels: filtros comunes en UI y consultas
        Schema::table('labels', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_labels_status_created');
            $table->index('printed_at', 'idx_labels_printed_at');
        });

        // Label batches: columna printed_at + índices
        Schema::table('label_batches', function (Blueprint $table) {
            $table->timestamp('printed_at')->nullable()->after('generated_at');
            $table->index('status', 'idx_label_batches_status');
            $table->index('printed_at', 'idx_label_batches_printed_at');
        });

        // Label logs: filtros de bitácora
        Schema::table('label_logs', function (Blueprint $table) {
            $table->index(['action', 'created_at'], 'idx_label_logs_action_created');
        });

        // Customers: búsquedas frecuentes desde garantías
        Schema::table('customers', function (Blueprint $table) {
            $table->index('phone', 'idx_customers_phone');
            $table->index('city', 'idx_customers_city');
            $table->index('document_number', 'idx_customers_document_number');
            $table->index(['last_name', 'first_name'], 'idx_customers_name');
        });
    }

    public function down(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->dropIndex('idx_labels_status_created');
            $table->dropIndex('idx_labels_printed_at');
        });

        Schema::table('label_batches', function (Blueprint $table) {
            $table->dropColumn('printed_at');
            $table->dropIndex('idx_label_batches_status');
            $table->dropIndex('idx_label_batches_printed_at');
        });

        Schema::table('label_logs', function (Blueprint $table) {
            $table->dropIndex('idx_label_logs_action_created');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_phone');
            $table->dropIndex('idx_customers_city');
            $table->dropIndex('idx_customers_document_number');
            $table->dropIndex('idx_customers_name');
        });
    }
};
