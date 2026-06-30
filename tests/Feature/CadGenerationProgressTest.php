<?php

// #2475 — the DFM SSE stream now carries an `estimated_time_seconds` field.
// GenerateCadJob must forward it through CadGenerationProgress so the chatbot
// progress modal can show the user an estimated remaining time.

use Illuminate\Support\Facades\Event;
use Tolery\AiCad\Events\CadGenerationCompleted;
use Tolery\AiCad\Events\CadGenerationFailed;
use Tolery\AiCad\Events\CadGenerationProgress;
use Tolery\AiCad\Events\CadGenerationStarted;
use Tolery\AiCad\Jobs\GenerateCadJob;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Services\AICADClient;

/**
 * Build a fake AICADClient whose streamToCallback() replays a single progress
 * event, so we can assert how GenerateCadJob maps it onto the broadcast.
 *
 * @param  array<string, mixed>  $progressEvent
 */
function fakeAicadClientEmitting(array $progressEvent): AICADClient
{
    return new readonly class($progressEvent) extends AICADClient
    {
        /** @param array<string, mixed> $progressEvent */
        public function __construct(private array $progressEvent) {}

        public function streamToCallback(
            string $message,
            ?string $projectId,
            bool $isEditRequest,
            int $timeoutSec,
            string $materialChoice,
            int $priority,
            callable $onProgress,
            callable $onComplete,
            callable $onError,
        ): void {
            $onProgress($this->progressEvent);
        }
    };
}

describe('CadGenerationProgress broadcast payload', function () {
    it('broadcasts estimated_time_seconds when provided', function () {
        $message = new ChatMessage;
        $message->id = 1;
        $message->chat_id = 2;

        $payload = (new CadGenerationProgress($message, 'analysis', 10, 'Analyse…', 182))->broadcastWith();

        expect($payload)->toMatchArray([
            'message_id' => 1,
            'chat_id' => 2,
            'step' => 'analysis',
            'pct' => 10,
            'message' => 'Analyse…',
            'estimated_time_seconds' => 182,
        ]);
    });

    it('broadcasts a null estimated_time_seconds when omitted', function () {
        $message = new ChatMessage;
        $message->id = 1;
        $message->chat_id = 2;

        $payload = (new CadGenerationProgress($message, 'analysis', 10, 'Analyse…'))->broadcastWith();

        expect($payload)->toHaveKey('estimated_time_seconds', null);
    });
});

describe('GenerateCadJob forwards estimated_time_seconds', function () {
    beforeEach(function () {
        Event::fake([
            CadGenerationStarted::class,
            CadGenerationProgress::class,
            CadGenerationCompleted::class,
            CadGenerationFailed::class,
        ]);

        config(['ai-cad.chat_team_model' => ChatTeam::class]);

        $chat = Chat::factory()->create(['team_id' => ChatTeam::factory()->create()->id]);
        $this->message = ChatMessage::create([
            'chat_id' => $chat->id,
            'message' => '[TYPING_INDICATOR]',
        ]);
    });

    it('reads estimated_time_seconds from the SSE event and broadcasts it', function () {
        $client = fakeAicadClientEmitting([
            'step' => 'generation_code',
            'overall_percentage' => 55,
            'message' => 'Génération…',
            'estimated_time_seconds' => 182,
        ]);

        (new GenerateCadJob(
            messageId: $this->message->id,
            userMessage: 'plaque',
            sessionId: null,
            isEditRequest: false,
            materialChoice: 'STEEL',
        ))->handle($client);

        Event::assertDispatched(
            CadGenerationProgress::class,
            fn (CadGenerationProgress $e) => $e->message->id === $this->message->id
                && $e->estimatedTimeSeconds === 182
        );
    });

    it('broadcasts a null estimated_time_seconds when the SSE event omits it', function () {
        $client = fakeAicadClientEmitting([
            'step' => 'analysis',
            'overall_percentage' => 10,
            'message' => 'Analyse…',
        ]);

        (new GenerateCadJob(
            messageId: $this->message->id,
            userMessage: 'plaque',
            sessionId: null,
            isEditRequest: false,
            materialChoice: 'STEEL',
        ))->handle($client);

        Event::assertDispatched(
            CadGenerationProgress::class,
            fn (CadGenerationProgress $e) => $e->estimatedTimeSeconds === null
        );
    });
});
