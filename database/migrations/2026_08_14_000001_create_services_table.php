<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('command');
            $table->string('working_directory')->nullable();
            $table->string('user')->nullable();
            $table->string('environment')->default('development');
            $table->unsignedInteger('port')->nullable();
            $table->json('env_vars')->nullable();
            $table->boolean('auto_start_on_boot')->default(false);
            $table->boolean('auto_restart')->default(false);
            $table->string('restart_policy')->default('manual');
            $table->unsignedInteger('max_restarts')->default(5);
            $table->unsignedInteger('stop_timeout')->default(10);
            $table->string('status')->default('stopped');
            $table->unsignedBigInteger('pid')->nullable();
            $table->integer('exit_code')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->float('cpu_usage')->nullable();
            $table->unsignedBigInteger('memory_kb')->nullable();
            $table->unsignedInteger('uptime_seconds')->nullable();
            $table->boolean('port_open')->nullable();
            $table->timestamp('last_metrics_at')->nullable();
            $table->timestamp('last_restart_at')->nullable();
            $table->unsignedInteger('restart_count')->default(0);
            $table->text('last_error')->nullable();
            $table->string('log_path_out')->nullable();
            $table->string('log_path_err')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['environment', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
