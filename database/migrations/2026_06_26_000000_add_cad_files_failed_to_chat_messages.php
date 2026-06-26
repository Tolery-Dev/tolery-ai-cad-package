<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // true = le téléchargement des assets (DownloadCadAssetsJob) a échoué
            // définitivement après épuisement des tentatives. Distinct de
            // cad_files_ready (= assets disponibles localement) : permet de
            // prévenir l'utilisateur et de proposer un nouvel essai plutôt que de
            // laisser la modal « préparation en cours » tourner indéfiniment (#2438).
            $table->boolean('cad_files_failed')->default(false)->after('cad_files_ready');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('cad_files_failed');
        });
    }
};
