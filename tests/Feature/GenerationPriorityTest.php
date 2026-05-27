<?php

use Tolery\AiCad\Jobs\GenerateCadJob;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\SubscriptionProduct;

beforeEach(function () {
    config(['ai-cad.chat_team_model' => ChatTeam::class]);
});

/**
 * Attaches an active subscription on $team pointing at $product.
 */
function subscribeTeamToProduct(ChatTeam $team, SubscriptionProduct $product): void
{
    $subscription = $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_'.bin2hex(random_bytes(4)),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);

    $subscription->items()->create([
        'stripe_id' => 'si_'.bin2hex(random_bytes(4)),
        'stripe_product' => $product->stripe_id,
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);
}

describe('GenerateCadJob::queueForPriority', function () {
    it('maps priority to the weighted queue', function (int $priority, string $expected) {
        expect(GenerateCadJob::queueForPriority($priority))->toBe($expected);
    })->with([
        'free (0)' => [0, 'tolerycad-long-low'],
        'low paid (1)' => [1, 'tolerycad-long-normal'],
        'mid (69)' => [69, 'tolerycad-long-normal'],
        'high threshold (70)' => [70, 'tolerycad-long-high'],
        'max (100)' => [100, 'tolerycad-long-high'],
    ]);

    it('routes the dispatched job onto the queue matching its priority', function () {
        $job = new GenerateCadJob(
            messageId: 1,
            userMessage: 'plaque',
            sessionId: null,
            isEditRequest: false,
            materialChoice: 'STEEL',
            priority: 80,
        );

        expect($job->queue)->toBe('tolerycad-long-high');
    });

    it('defaults to the low queue when no priority is given', function () {
        $job = new GenerateCadJob(
            messageId: 1,
            userMessage: 'plaque',
            sessionId: null,
            isEditRequest: false,
            materialChoice: 'STEEL',
        );

        expect($job->queue)->toBe('tolerycad-long-low');
    });
});

describe('HasSubscription::getGenerationPriority', function () {
    it('returns 0 for a team without an active subscription', function () {
        $team = ChatTeam::factory()->create();

        expect($team->getGenerationPriority())->toBe(0);
    });

    it('returns the product priority for a subscribed team', function () {
        $product = SubscriptionProduct::factory()->create([
            'stripe_id' => 'prod_high',
            'priority' => 80,
            'active' => true,
        ]);
        $team = ChatTeam::factory()->create();
        subscribeTeamToProduct($team, $product);

        expect($team->getGenerationPriority())->toBe(80);
    });

    it('falls back to the paid-tier default when the product has no priority', function () {
        $product = SubscriptionProduct::factory()->create([
            'stripe_id' => 'prod_no_priority',
            'priority' => null,
            'active' => true,
        ]);
        $team = ChatTeam::factory()->create();
        subscribeTeamToProduct($team, $product);

        expect($team->getGenerationPriority())
            ->toBe(ChatTeam::DEFAULT_PAID_GENERATION_PRIORITY);
    });
});
