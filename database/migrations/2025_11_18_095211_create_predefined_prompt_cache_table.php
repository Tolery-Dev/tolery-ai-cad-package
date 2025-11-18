<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('predefined_prompt_cache', function (Blueprint $table) {
            $table->id();
            $table->string('prompt_hash', 32)->unique()->index();
            $table->text('prompt_text');
            $table->string('obj_cache_path')->nullable();
            $table->string('step_cache_path')->nullable();
            $table->string('json_cache_path')->nullable();
            $table->string('technical_drawing_cache_path')->nullable();
            $table->string('screenshot_cache_path')->nullable();
            $table->text('chat_response');
            $table->json('simulated_steps')->nullable();
            $table->unsignedBigInteger('hits_count')->default(0);
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predefined_prompt_cache');
    }
};
