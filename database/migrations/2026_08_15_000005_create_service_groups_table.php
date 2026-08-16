<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color')->default('primary');

            // Pausa entre um serviço e o próximo ao subir o grupo: dá tempo para
            // a dependência (ex.: o app) ficar de pé antes do dependente (queue).
            $table->unsignedInteger('start_delay_seconds')->default(2);
            $table->timestamps();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('service_group_id')
                ->nullable()
                ->after('slug')
                ->constrained('service_groups')
                ->nullOnDelete();

            // Ordem de subida dentro do grupo (a descida usa a ordem inversa).
            $table->unsignedInteger('boot_order')->default(0)->after('service_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['service_group_id']);
            $table->dropColumn(['service_group_id', 'boot_order']);
        });

        Schema::dropIfExists('service_groups');
    }
};
