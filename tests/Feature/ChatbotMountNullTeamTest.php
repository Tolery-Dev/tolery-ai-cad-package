<?php

use Illuminate\Support\Facades\Auth;
use Tolery\AiCad\Livewire\Chatbot;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Services\FileAccessService;

/**
 * Régression #375 : depuis l'ouverture GA de ToleryCAD à tous, un client SANS
 * équipe (team_id null) peut atteindre le chatbot. mount() passait $user->team
 * (null) à getQuotaStatus(ChatTeam $team) → TypeError → 500.
 */
beforeEach(function () {
    // Aucun modèle DFM configuré → loadDfmErrorCodes() retourne [] sans toucher la DB.
    config(['ai-cad.dfm_error_code_model' => null]);

    // Stub avec la signature RÉELLE de prod (ChatTeam NON-nullable) : si mount()
    // appelle getQuotaStatus(null) — le bug —, c'est un TypeError, soit le crash #375.
    app()->instance(FileAccessService::class, new class extends FileAccessService
    {
        public function __construct() {}

        public function getQuotaStatus(ChatTeam $team): ?array
        {
            return null;
        }
    });

    $user = new ChatUser(['name' => 'No Team User']);
    $user->setRelation('team', null);
    Auth::setUser($user);
});

it('ne crashe pas au mount quand le client n\'a pas d\'équipe (régression #375)', function () {
    $component = new Chatbot;
    $component->chat = new Chat;

    $component->mount();

    expect($component->quotaStatus)->toBeNull();
});
