<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->float('cpu_usage')->nullable();
            $table->unsignedBigInteger('memory_kb')->nullable();
            $table->boolean('port_open')->nullable();
            $table->timestamp('recorded_at');

            $table->index(['service_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_metrics');
    }
};
