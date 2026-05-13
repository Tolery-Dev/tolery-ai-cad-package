<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // Phase 2 — async CAD generation (issue #152). The columns track the lifecycle
            // of the GenerateCadJob so the chat can survive a tab close / reload: the user
            // can come back to find the piece ready (or still being generated).
            $table->string('generation_status', 16)->nullable()->after('cad_files_ready');
            $table->unsignedTinyInteger('generation_progress_pct')->nullable()->after('generation_status');
            $table->string('generation_progress_step', 64)->nullable()->after('generation_progress_pct');
            $table->string('generation_progress_message', 255)->nullable()->after('generation_progress_step');
            $table->timestamp('generation_started_at')->nullable()->after('generation_progress_message');
            $table->timestamp('generation_completed_at')->nullable()->after('generation_started_at');
            $table->text('generation_error')->nullable()->after('generation_completed_at');

            $table->index(['chat_id', 'generation_status'], 'chat_messages_chat_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('chat_messages_chat_status_idx');
            $table->dropColumn([
                'generation_status',
                'generation_progress_pct',
                'generation_progress_step',
                'generation_progress_message',
                'generation_started_at',
                'generation_completed_at',
                'generation_error',
            ]);
        });
    }
};
