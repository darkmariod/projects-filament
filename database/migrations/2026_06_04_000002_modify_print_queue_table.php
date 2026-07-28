<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_queue', function (Blueprint $table) {
            // ── Eliminar columnas que migran a print_queue_items ──
            $table->dropConstrainedForeignId('label_id');
            $table->dropColumn('zpl_content');

            // ── Renombrar sent_labels → printed_labels ──
            $table->renameColumn('sent_labels', 'printed_labels');

            // ── Agregar nuevas columnas ──
            $table->unsignedInteger('failed_labels')->default(0)->after('total_labels');
        });

        // Modificar el CHECK del status para incluir 'partial'
        // En SQLite (testing) no podemos alterar check constraints,
        // pero en MySQL/MariaDB/PostgreSQL sí funciona.
        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE print_queue MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'
                COMMENT 'pending, processing, completed, partial, failed, cancelled'");
        }
    }

    public function down(): void
    {
        Schema::table('print_queue', function (Blueprint $table) {
            $table->dropColumn('failed_labels');
            $table->renameColumn('printed_labels', 'sent_labels');

            $table->longText('zpl_content')->nullable();
            $table->foreignId('label_id')->nullable()->constrained('labels')->cascadeOnDelete();
        });
    }
};
