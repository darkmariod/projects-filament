<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_batches', function (Blueprint $table) {
            $table->dropColumn('serial_from');
            $table->dropColumn('serial_to');
        });
    }

    public function down(): void
    {
        Schema::table('label_batches', function (Blueprint $table) {
            $table->string('serial_from')->nullable()->after('observations');
            $table->string('serial_to')->nullable()->after('serial_from');
        });
    }
};
