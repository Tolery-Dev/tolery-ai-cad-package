<?php

// Regression test: BroadcastEvent reads $tries/$backoff from the wrapped event
// via property_exists(). Without these, a transient Reverb 502 sends the job
// straight to failed_jobs with no retry (Nightwatch #270).

use Illuminate\Broadcasting\BroadcastEvent;
use Tolery\AiCad\Events\CadGenerationCompleted;
use Tolery\AiCad\Events\CadGenerationFailed;
use Tolery\AiCad\Events\CadGenerationProgress;
use Tolery\AiCad\Events\CadGenerationStarted;

describe('CadGeneration broadcast events — BroadcastEvent retry config', function () {
    it('CadGenerationCompleted exposes tries=3 and backoff=5 to BroadcastEvent', function () {
        $ref = new ReflectionClass(CadGenerationCompleted::class);

        expect($ref->getProperty('tries')->getDefaultValue())->toBe(3)
            ->and($ref->getProperty('backoff')->getDefaultValue())->toBe(5);
    });

    it('CadGenerationFailed exposes tries=3 and backoff=5 to BroadcastEvent', function () {
        $ref = new ReflectionClass(CadGenerationFailed::class);

        expect($ref->getProperty('tries')->getDefaultValue())->toBe(3)
            ->and($ref->getProperty('backoff')->getDefaultValue())->toBe(5);
    });

    it('CadGenerationProgress exposes tries=3 and backoff=5 to BroadcastEvent', function () {
        $ref = new ReflectionClass(CadGenerationProgress::class);

        expect($ref->getProperty('tries')->getDefaultValue())->toBe(3)
            ->and($ref->getProperty('backoff')->getDefaultValue())->toBe(5);
    });

    it('CadGenerationStarted exposes tries=3 and backoff=5 to BroadcastEvent', function () {
        $ref = new ReflectionClass(CadGenerationStarted::class);

        expect($ref->getProperty('tries')->getDefaultValue())->toBe(3)
            ->and($ref->getProperty('backoff')->getDefaultValue())->toBe(5);
    });

    it('BroadcastEvent constructor picks up tries and backoff from an event', function () {
        $event = new class {
            public int $tries = 3;

            public int $backoff = 5;
        };

        $job = new BroadcastEvent($event);

        expect($job->tries)->toBe(3)
            ->and($job->backoff)->toBe(5);
    });
});
