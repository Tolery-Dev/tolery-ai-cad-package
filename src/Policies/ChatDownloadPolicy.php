<?php

namespace Tolery\AiCad\Policies;

use Tolery\AiCad\Models\ChatDownload;
use Tolery\AiCad\Models\ChatUser;

class ChatDownloadPolicy
{
    /**
     * Détermine si l'utilisateur peut voir le téléchargement en tant qu'admin.
     */
    public function viewAsAdmin(ChatUser $user, ChatDownload $download): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Détermine si l'utilisateur peut voir la liste des téléchargements.
     */
    public function viewAny(ChatUser $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Détermine si l'utilisateur peut télécharger les fichiers.
     */
    public function downloadFiles(ChatUser $user, ChatDownload $download): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Vérifie si l'utilisateur est admin.
     */
    protected function isAdmin(ChatUser $user): bool
    {
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin');
        }

        return $user->is_admin ?? false;
    }
}
