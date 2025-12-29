<?php

namespace Tolery\AiCad\Policies;

use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatUser;

class ChatPolicy
{
    /**
     * Détermine si l'utilisateur peut voir le chat en tant qu'admin.
     */
    public function viewAsAdmin(ChatUser $user, Chat $chat): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Détermine si l'utilisateur peut télécharger les fichiers du chat.
     */
    public function downloadFiles(ChatUser $user, Chat $chat): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Détermine si l'utilisateur peut voir la liste des chats.
     */
    public function viewAny(ChatUser $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Vérifie si l'utilisateur est admin.
     */
    protected function isAdmin(ChatUser $user): bool
    {
        // Vérifier via la méthode hasRole si elle existe
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin');
        }

        // Fallback sur la propriété is_admin
        return $user->is_admin ?? false;
    }
}
