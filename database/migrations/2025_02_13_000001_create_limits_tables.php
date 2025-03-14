<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('ai-cad.usage-limiter.tables.limits'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('plan')->nullable();
            $table->decimal('allowed_amount', 11, 4);
            $table->string('reset_frequency')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'plan']);
        });

        Schema::create(config('ai-cad.usage-limiter.tables.model_has_limits'), function (Blueprint $table) {
            $table->id();

            $table->foreignId(config('ai-cad.usage-limiter.columns.limit_pivot_key'))
                ->nullable()
                ->references('id')
                ->on(config('ai-cad.usage-limiter.tables.limits'))
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->morphs('model');
            $table->decimal('used_amount', 11, 4);
            $table->dateTime('last_reset')->nullable();
            $table->dateTime('next_reset')->nullable();
            $table->timestamps();

            $table->unique([
                'model_type',
                'model_id',
                config('ai-cad.usage-limiter.columns.limit_pivot_key'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ai-cad.usage-limiter.tables.model_has_limits'));
        Schema::dropIfExists(config('ai-cad.usage-limiter.tables.limits'));
    }
};
