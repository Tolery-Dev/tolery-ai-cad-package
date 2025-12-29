<?php

namespace Tolery\AiCad\Policies;

use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Models\FilePurchase;

class FilePurchasePolicy
{
    /**
     * Détermine si l'utilisateur peut voir l'achat en tant qu'admin.
     */
    public function viewAsAdmin(ChatUser $user, FilePurchase $purchase): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Détermine si l'utilisateur peut voir la liste des achats.
     */
    public function viewAny(ChatUser $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Détermine si l'utilisateur peut télécharger les fichiers de l'achat.
     */
    public function downloadFiles(ChatUser $user, FilePurchase $purchase): bool
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
