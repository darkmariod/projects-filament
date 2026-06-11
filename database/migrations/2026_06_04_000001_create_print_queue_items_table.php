<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_queue_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('print_queue_id')
                ->constrained('print_queue')
                ->cascadeOnDelete();

            $table->foreignId('label_id')
                ->constrained('labels')
                ->cascadeOnDelete();

            $table->unsignedInteger('sequence')->default(0);

            $table->longText('zpl_content')->nullable();

            // pending, printing, printed, failed, cancelled
            $table->string('status')->default('pending');

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);

            $table->text('error_message')->nullable();

            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            // Un item por label dentro de una misma cola
            $table->unique(['print_queue_id', 'label_id'], 'uq_print_queue_item_label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_queue_items');
    }
};
