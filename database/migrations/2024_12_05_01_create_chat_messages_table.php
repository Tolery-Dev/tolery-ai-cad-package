<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();

            $table->string('ai_cad_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
