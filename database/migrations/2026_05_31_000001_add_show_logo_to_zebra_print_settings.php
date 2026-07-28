<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zebra_print_settings', function (Blueprint $table) {
            $table->boolean('show_logo')->default(true)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('zebra_print_settings', function (Blueprint $table) {
            $table->dropColumn('show_logo');
        });
    }
};
