<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\ChatUser;

uses(RefreshDatabase::class);

// Skip all tests in this file until proper test migrations are created
test('placeholder', function () {
    expect(true)->toBeTrue();
})->skip('StreamController tests require full Laravel app with users/teams migrations');

return;

beforeEach(function () {
    // Mock user and team for authenticated route
    $team = ChatTeam::create([
        'name' => 'Test Team',
    ]);

    $this->user = ChatUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'team_id' => $team->id,
    ]);

    $this->chat = Chat::create([
        'team_id' => $team->id,
        'user_id' => $this->user->id,
        'name' => 'Test Chat',
    ]);
});

it('requires authentication to access streaming endpoint', function () {
    $response = $this->postJson(route('ai-cad.stream.generate-cad'), [
        'message' => 'Create a 100x100x5mm steel plate',
        'session_id' => null,
    ]);

    $response->assertStatus(401);
})->skip('Requires full Laravel app with users table');

it('validates required message field', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('ai-cad.stream.generate-cad'), [
            'session_id' => null,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['message']);
})->skip('Requires full Laravel app with users table');

it('returns streaming response with valid request', function () {
    // Skip this test if API credentials are not configured
    if (! config('ai-cad.api.key')) {
        $this->markTestSkipped('AICAD_API_KEY not configured');
    }

    $response = $this->actingAs($this->user)
        ->post(route('ai-cad.stream.generate-cad'), [
            'message' => 'Create a simple 50x50x2mm steel plate',
            'session_id' => null,
        ]);

    // SSE response should be 200 with proper content type
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/event-stream');
})->skip('This test requires real API credentials and takes time');

it('handles session_id correctly', function () {
    if (! config('ai-cad.api.key')) {
        $this->markTestSkipped('AICAD_API_KEY not configured');
    }

    $response = $this->actingAs($this->user)
        ->postJson(route('ai-cad.stream.generate-cad'), [
            'message' => 'Test message',
            'session_id' => 'test-session-123',
        ]);

    $response->assertStatus(200);
})->skip('This test requires real API credentials and takes time');
