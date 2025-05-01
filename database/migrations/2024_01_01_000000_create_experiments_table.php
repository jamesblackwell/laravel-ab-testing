<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('experiments')) {
            Schema::create('experiments', function (Blueprint $table) {
                $table->id();
                $table->string('experiment_name');
                $table->string('variant');
                $table->bigInteger('total_views')->default(0);
                $table->bigInteger('conversions')->default(0);
                $table->bigInteger('secondary_conversions')->default(0);
                $table->timestamps();

                $table->unique(['experiment_name', 'variant']); // Ensure uniqueness
                $table->index('experiment_name');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('experiments');
    }
};