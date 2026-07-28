<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zebra_print_settings', function (Blueprint $table) {
            $table->string('printer_ip', 45)->nullable()->after('active')
                ->comment('IP de la impresora Zebra en la red');
            $table->integer('printer_port')->unsigned()->default(9100)->after('printer_ip')
                ->comment('Puerto TCP estándar Zebra (9100)');
            $table->integer('chunk_size')->unsigned()->default(500)->after('printer_port')
                ->comment('Etiquetas por bloque al imprimir lotes grandes');
        });
    }

    public function down(): void
    {
        Schema::table('zebra_print_settings', function (Blueprint $table) {
            $table->dropColumn(['printer_ip', 'printer_port', 'chunk_size']);
        });
    }
};
