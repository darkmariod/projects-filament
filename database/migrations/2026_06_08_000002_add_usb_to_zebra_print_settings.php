<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zebra_print_settings', function (Blueprint $table) {
            $table->string('connection_type', 20)->default('network')->after('id')
                ->comment('network (TCP/IP) o usb (CUPS)');
            $table->string('printer_name')->nullable()->after('connection_type')
                ->comment('Nombre de la cola CUPS para impresión USB');
        });
    }

    public function down(): void
    {
        Schema::table('zebra_print_settings', function (Blueprint $table) {
            $table->dropColumn(['connection_type', 'printer_name']);
        });
    }
};
