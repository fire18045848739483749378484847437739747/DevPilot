<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Byte a partir do qual o ingestor deve ler o arquivo de log na
            // próxima passagem. Persistido para que a captura funcione tanto no
            // agente quanto no painel (processos diferentes a cada requisição).
            $table->unsignedBigInteger('log_offset_out')->default(0)->after('log_path_out');
            $table->unsignedBigInteger('log_offset_err')->default(0)->after('log_path_err');
        });

        Schema::table('service_logs', function (Blueprint $table) {
            // `type` diz de onde veio (stdout/stderr/system); `level` diz a
            // gravidade, inferida do conteúdo da linha. São coisas diferentes:
            // o `php -S` escreve o log de acesso (inclusive 200) no stderr.
            $table->string('level', 20)->default('info')->after('type');

            $table->index(['service_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['log_offset_out', 'log_offset_err']);
        });

        Schema::table('service_logs', function (Blueprint $table) {
            $table->dropIndex(['service_id', 'level']);
            $table->dropColumn('level');
        });
    }
};
