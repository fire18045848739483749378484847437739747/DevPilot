<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Total de segundos de CPU consumidos, lido do Get-Process na última
            // coleta. Persistido (em vez de mantido em memória) para que o cálculo
            // de CPU% funcione também fora do agente — ex.: no painel web, que
            // roda em um processo diferente a cada requisição.
            $table->double('cpu_seconds_total')->nullable()->after('cpu_usage');

            // Esquema/caminho usados para montar o link de acesso do serviço.
            $table->string('url_scheme')->default('http')->after('port_open');
            $table->string('url_path')->nullable()->after('url_scheme');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['cpu_seconds_total', 'url_scheme', 'url_path']);
        });
    }
};
