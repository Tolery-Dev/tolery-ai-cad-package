<?php

namespace Tolery\AiCad\Policies;

use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Models\StepMessage;

class StepMessagePolicy
{
    /**
     * Determine si l'utilisateur peut voir la liste des messages d'etape.
     */
    public function viewAny(ChatUser $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine si l'utilisateur peut creer un message d'etape.
     */
    public function create(ChatUser $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine si l'utilisateur peut modifier un message d'etape.
     */
    public function update(ChatUser $user, StepMessage $stepMessage): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine si l'utilisateur peut supprimer un message d'etape.
     */
    public function delete(ChatUser $user, StepMessage $stepMessage): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Verifie si l'utilisateur est admin.
     */
    protected function isAdmin(ChatUser $user): bool
    {
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin');
        }

        return $user->is_admin ?? false;
    }
}
