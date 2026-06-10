<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tolery\AiCad\Livewire\Chatbot;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Services\FileAccessService;

/**
 * Régression #2338 : sur un ghost chat (nouveau fichier), mount() sortait avant
 * de charger $dfmErrorCodes. Le chat étant ensuite créé en lazy dans send()
 * (URL mise à jour via History API, sans remount), la propriété restait vide
 * pour toute la session et le front affichait le code DFM brut (ex: « 104.1 »)
 * au lieu du message utilisateur.
 */
class ChatbotMountTestDfmErrorCode extends Model
{
    protected $table = 'dfm_error_codes';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('dfm_error_codes', function (Blueprint $table) {
        $table->id();
        $table->string('code');
        $table->string('message_fr')->nullable();
        $table->string('message_en')->nullable();
        $table->timestamps();
    });

    ChatbotMountTestDfmErrorCode::create([
        'code' => '104.1',
        'message_fr' => "Échec d'exécution lors de la construction 3D.",
        'message_en' => 'Execution failure during 3D construction.',
    ]);

    config(['ai-cad.dfm_error_code_model' => ChatbotMountTestDfmErrorCode::class]);

    // Stub : évite la résolution d'AiCadStripe et le calcul de quota,
    // hors du périmètre de ce test.
    app()->instance(FileAccessService::class, new class extends FileAccessService
    {
        public function __construct() {}

        public function getQuotaStatus(?ChatTeam $team): ?array
        {
            return null;
        }
    });

    $user = new ChatUser(['name' => 'Test User']);
    $user->setRelation('team', null);
    Auth::setUser($user);
});

function mountChatbotWithGhostChat(): Chatbot
{
    $component = new Chatbot;
    $component->chat = new Chat;
    $component->mount();

    return $component;
}

it('charge les codes erreurs DFM au mount même sur un ghost chat (régression #2338)', function () {
    app()->setLocale('fr');

    $component = mountChatbotWithGhostChat();

    expect($component->dfmErrorCodes)
        ->toBe(['104.1' => "Échec d'exécution lors de la construction 3D."]);
});

it('conserve le early return ghost chat : pas de messages chargés', function () {
    app()->setLocale('fr');

    $component = mountChatbotWithGhostChat();

    expect($component->messages)->toBe([]);
});

it('utilise message_en pour une locale non française', function () {
    app()->setLocale('en');

    $component = mountChatbotWithGhostChat();

    expect($component->dfmErrorCodes)
        ->toBe(['104.1' => 'Execution failure during 3D construction.']);
});
