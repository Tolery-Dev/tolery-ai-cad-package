<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_downloads', function (Blueprint $table) {
            // Supprime l'ancienne contrainte unique
            $table->dropUnique(['team_id', 'chat_id']);

            // Ajoute la colonne message_id (nullable pour la compatibilité avec les anciens téléchargements)
            $table->foreignId('message_id')->nullable()->after('chat_id')->constrained('chat_messages')->onDelete('cascade');

            // Nouvelle contrainte unique: une version spécifique ne peut être téléchargée qu'une fois par équipe
            // Si message_id est null (téléchargement du chat complet), on utilise seulement team_id + chat_id
            $table->unique(['team_id', 'chat_id', 'message_id'], 'team_chat_message_unique');
        });
    }

    public function down(): void
    {
        Schema::table('chat_downloads', function (Blueprint $table) {
            $table->dropUnique('team_chat_message_unique');
            $table->dropForeign(['message_id']);
            $table->dropColumn('message_id');

            // Restaure l'ancienne contrainte
            $table->unique(['team_id', 'chat_id']);
        });
    }
};
