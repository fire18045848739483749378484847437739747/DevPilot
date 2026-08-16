<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Health check HTTP: porta aberta não significa aplicação de pé.
            $table->boolean('health_check_enabled')->default(false)->after('port_open');
            $table->string('health_check_path')->nullable()->after('health_check_enabled');
            $table->unsignedSmallInteger('health_check_status')->default(200)->after('health_check_path');
            $table->unsignedSmallInteger('health_check_timeout')->default(5)->after('health_check_status');
            $table->string('health_status')->nullable()->after('health_check_timeout');
            $table->unsignedSmallInteger('health_last_code')->nullable()->after('health_status');
            $table->text('health_error')->nullable()->after('health_last_code');
            $table->unsignedInteger('health_failures')->default(0)->after('health_error');
            $table->timestamp('health_checked_at')->nullable()->after('health_failures');

            // Alertas: limites por serviço (nulo = usa apenas os eventos de
            // queda/limite de reinícios, sem alerta de recurso).
            $table->boolean('alerts_enabled')->default(true)->after('health_checked_at');
            $table->float('alert_cpu_threshold')->nullable()->after('alerts_enabled');
            $table->unsignedInteger('alert_memory_threshold_mb')->nullable()->after('alert_cpu_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'health_check_enabled',
                'health_check_path',
                'health_check_status',
                'health_check_timeout',
                'health_status',
                'health_last_code',
                'health_error',
                'health_failures',
                'health_checked_at',
                'alerts_enabled',
                'alert_cpu_threshold',
                'alert_memory_threshold_mb',
            ]);
        });
    }
};
