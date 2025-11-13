<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('chat_id')->constrained('chats')->onDelete('cascade');
            $table->timestamp('downloaded_at');
            $table->timestamps();

            // Un chat ne peut être téléchargé qu'une fois par équipe
            $table->unique(['team_id', 'chat_id']);

            $table->index(['team_id', 'downloaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_downloads');
    }
};
