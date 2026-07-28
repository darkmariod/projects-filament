<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zebra_print_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('printer_model')->default('Zebra ZT411');
            $table->integer('dpi')->default(203);
            $table->decimal('label_width_mm', 8, 2);
            $table->decimal('label_height_mm', 8, 2);
            $table->decimal('label_gap_mm', 8, 2)->nullable();
            $table->integer('width_dots');
            $table->integer('height_dots');
            $table->integer('margin_x')->default(20);
            $table->integer('margin_y')->default(20);
            $table->integer('qr_size')->default(6);
            $table->integer('barcode_height')->default(120);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zebra_print_settings');
    }
};