<?php

namespace Tolery\AiCad\Policies;

use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Models\PredefinedPrompt;

class PredefinedPromptPolicy
{
    /**
     * Détermine si l'utilisateur peut voir la liste des prompts.
     */
    public function viewAny(ChatUser $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Détermine si l'utilisateur peut créer un prompt.
     */
    public function create(ChatUser $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Détermine si l'utilisateur peut modifier un prompt.
     */
    public function update(ChatUser $user, PredefinedPrompt $prompt): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Détermine si l'utilisateur peut supprimer un prompt.
     */
    public function delete(ChatUser $user, PredefinedPrompt $prompt): bool
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
