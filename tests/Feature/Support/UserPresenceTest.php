<?php

use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Support\UserPresence;

test('user is offline when null', function () {
    expect(UserPresence::isOnline(null))->toBeFalse();
});

test('user is offline when last_seen_at is null', function () {
    $user = new ChatUser;
    $user->last_seen_at = null;

    expect(UserPresence::isOnline($user))->toBeFalse();
});

test('user is offline when last_seen_at is older than the threshold', function () {
    config()->set('ai-cad.notifications.online_threshold_seconds', 30);

    $user = new ChatUser;
    $user->last_seen_at = now()->subSeconds(120);

    expect(UserPresence::isOnline($user))->toBeFalse();
});

test('user is online when last_seen_at is within the threshold', function () {
    config()->set('ai-cad.notifications.online_threshold_seconds', 30);

    $user = new ChatUser;
    $user->last_seen_at = now()->subSeconds(10);

    expect(UserPresence::isOnline($user))->toBeTrue();
});

test('user is online just inside the threshold boundary', function () {
    config()->set('ai-cad.notifications.online_threshold_seconds', 30);

    $user = new ChatUser;
    // Half a threshold worth of slack — Carbon::diffInSeconds returns a float
    // so testing at the exact boundary is racy.
    $user->last_seen_at = now()->subSeconds(29);

    expect(UserPresence::isOnline($user))->toBeTrue();
});

test('threshold is configurable', function () {
    config()->set('ai-cad.notifications.online_threshold_seconds', 5);

    $user = new ChatUser;
    $user->last_seen_at = now()->subSeconds(10);

    expect(UserPresence::isOnline($user))->toBeFalse();

    config()->set('ai-cad.notifications.online_threshold_seconds', 60);

    expect(UserPresence::isOnline($user))->toBeTrue();
});

test('string last_seen_at is parsed gracefully', function () {
    config()->set('ai-cad.notifications.online_threshold_seconds', 30);

    $user = new ChatUser;
    $user->last_seen_at = now()->subSeconds(10)->toDateTimeString();

    expect(UserPresence::isOnline($user))->toBeTrue();
});

test('garbage last_seen_at returns false instead of throwing', function () {
    $user = new ChatUser;
    $user->last_seen_at = 'not-a-date';

    expect(UserPresence::isOnline($user))->toBeFalse();
});
