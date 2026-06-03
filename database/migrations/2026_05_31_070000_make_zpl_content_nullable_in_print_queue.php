<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_queue', function (Blueprint $table) {
            $table->longText('zpl_content')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('print_queue', function (Blueprint $table) {
            $table->longText('zpl_content')->nullable(false)->change();
        });
    }
};
