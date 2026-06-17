<?php

use Stripe\Customer;
use Stripe\StripeClient;
use Tolery\AiCad\Services\AiCadStripe;

/**
 * Garantit que les customers Stripe ToleryCAD portent `preferred_locales = ['fr-FR']`,
 * sans quoi Stripe émet les factures PDF dans la langue par défaut du compte (anglais).
 * cf. mn-tolery#2364.
 */
function fakeStripeClientWithCustomers(object $customers): StripeClient
{
    $client = Mockery::mock(StripeClient::class);
    $client->customers = $customers;

    return $client;
}

function aiCadStripeUsing(StripeClient $client): AiCadStripe
{
    $service = Mockery::mock(AiCadStripe::class)->makePartial();
    $service->shouldReceive('client')->andReturn($client);

    return $service;
}

it('forces the fr-FR preferred locale when creating a Stripe customer', function () {
    $captured = null;

    $customers = Mockery::mock();
    $customers->shouldReceive('create')
        ->once()
        ->andReturnUsing(function (array $data) use (&$captured) {
            $captured = $data;

            return Customer::constructFrom(['id' => 'cus_new']);
        });

    $service = aiCadStripeUsing(fakeStripeClientWithCustomers($customers));

    $customer = $service->createOrUpdateCustomer('client@example.test', 'ACME SARL');

    expect($customer->id)->toBe('cus_new')
        ->and($captured['preferred_locales'])->toBe(['fr-FR']);
});

it('forces the fr-FR preferred locale when updating an existing Stripe customer', function () {
    $captured = null;

    $customers = Mockery::mock();
    $customers->shouldReceive('update')
        ->once()
        ->andReturnUsing(function (string $id, array $data) use (&$captured) {
            $captured = $data;

            return Customer::constructFrom(['id' => $id]);
        });

    $service = aiCadStripeUsing(fakeStripeClientWithCustomers($customers));

    $customer = $service->createOrUpdateCustomer(
        'client@example.test',
        'ACME SARL',
        'cus_existing',
        ['line1' => '1 rue de Paris'],
    );

    expect($customer->id)->toBe('cus_existing')
        ->and($captured['preferred_locales'])->toBe(['fr-FR'])
        ->and($captured['address'])->toBe(['line1' => '1 rue de Paris']);
});
