<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_batch_id')->nullable()->constrained('label_batches')->onDelete('cascade');
            $table->foreignId('label_id')->nullable()->constrained('labels')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->longText('zpl_content');
            $table->string('zebra_ip');
            $table->integer('zebra_port')->default(9100);
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->integer('total_labels')->default(0);
            $table->integer('sent_labels')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_queue');
    }
};
