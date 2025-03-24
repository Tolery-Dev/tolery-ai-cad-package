<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('ai-cad.usage-limiter.tables.model_has_limits'), function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_product_id')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('team_id')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->decimal('used_amount', 11, 4);
            $table->dateTime('last_reset')->nullable();
            $table->dateTime('next_reset')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ai-cad.usage-limiter.tables.model_has_limits'));
    }
};
