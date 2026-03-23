<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // true = fichiers CAO disponibles localement (téléchargés par DownloadCadAssetsJob)
            // false = téléchargement en attente (job dispatché, fichiers pas encore prêts)
            $table->boolean('cad_files_ready')->default(true)->after('ai_screenshot_path');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('cad_files_ready');
        });
    }
};
