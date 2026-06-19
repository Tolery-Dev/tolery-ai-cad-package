<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tolery\AiCad\Livewire\Chatbot;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\ChatUser;

beforeEach(function () {
    config(['ai-cad.chat_team_model' => ChatTeam::class]);
    config(['ai-cad.chat_user_model' => ChatUser::class]);
    View::prependNamespace('ai-cad', __DIR__.'/../stubs/ai-cad');
});

it('boots chatbot via Livewire::test with stub view', function () {
    $team = ChatTeam::factory()->create();
    $user = ChatUser::create(['team_id' => $team->id]);
    Auth::setUser($user);
    $chat = Chat::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);

    Livewire::test(Chatbot::class, ['chat' => $chat])
        ->assertSet('showPreparingModal', false);
});
